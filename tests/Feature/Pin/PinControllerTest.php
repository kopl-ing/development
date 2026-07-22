<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Pin\Pin;

function personWithPinPermission(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Moderators']);
    $group->givePermissionTo('kopling-pin::pin-moments');
    $person->groups()->attach($group);

    return $person;
}

function momentBy(Person $author): Moment
{
    return Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
}

it('denies a guest entirely', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = momentBy($author);

    $this->post("/_xhr/kopling-pin/{$moment->id}", ['reason' => 'announcement'])->assertRedirect();

    $this->assertGuest();
    expect(Pin::where('moment_id', $moment->id)->exists())->toBeFalse();
});

it('denies a person without pin-moments', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = momentBy($author);

    $this->actingAs($author)
        ->post("/_xhr/kopling-pin/{$moment->id}", ['reason' => 'announcement'])
        ->assertForbidden();
});

it('pins a moment, creating a pins row', function () {
    $operator = personWithPinPermission();
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = momentBy($author);

    $this->actingAs($operator)
        ->post("/_xhr/kopling-pin/{$moment->id}", ['reason' => 'announcement'])
        ->assertRedirect();

    expect(Pin::where('moment_id', $moment->id)->where('reason', 'announcement')->exists())->toBeTrue();
});

it('re-pinning updates the same row rather than creating a second one', function () {
    $operator = personWithPinPermission();
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = momentBy($author);

    $this->actingAs($operator)->post("/_xhr/kopling-pin/{$moment->id}", ['reason' => 'announcement']);
    $this->actingAs($operator)->post("/_xhr/kopling-pin/{$moment->id}", ['reason' => 'important']);

    expect(Pin::where('moment_id', $moment->id)->count())->toBe(1)
        ->and(Pin::where('moment_id', $moment->id)->first()->reason)->toBe('important');
});

it('unpins a moment, deleting its pin row', function () {
    $operator = personWithPinPermission();
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = momentBy($author);

    $this->actingAs($operator)->post("/_xhr/kopling-pin/{$moment->id}", ['reason' => 'announcement']);
    $this->actingAs($operator)->post("/_xhr/kopling-pin/{$moment->id}/unpin")->assertRedirect();

    expect(Pin::where('moment_id', $moment->id)->exists())->toBeFalse();
});

it('a Groups-targeted pin is only visible to a person in one of those groups', function () {
    $operator = personWithPinPermission();
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = momentBy($author);
    $targetGroup = Group::create(['name' => 'VIPs']);

    $this->actingAs($operator)->post("/_xhr/kopling-pin/{$moment->id}", [
        'reason' => 'announcement',
        'groups' => [$targetGroup->id],
    ]);

    $outsider = Person::create(['name' => 'Eve', 'email' => 'eve@example.test', 'password' => 'secret']);
    $insider = Person::create(['name' => 'Zoe', 'email' => 'zoe@example.test', 'password' => 'secret']);
    $insider->groups()->attach($targetGroup);

    expect(Pin::visibleFor($outsider))->toHaveCount(0)
        ->and(Pin::visibleFor($insider))->toHaveCount(1)
        ->and(Pin::visibleFor(null))->toHaveCount(0);
});

it('an expired pin is excluded from visibleFor()', function () {
    $operator = personWithPinPermission();
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = momentBy($author);

    $this->actingAs($operator)->post("/_xhr/kopling-pin/{$moment->id}", [
        'reason' => 'announcement',
        'ends_at' => now()->subDay()->format('Y-m-d\TH:i'),
    ]);

    expect(Pin::visibleFor($operator))->toHaveCount(0);
});
