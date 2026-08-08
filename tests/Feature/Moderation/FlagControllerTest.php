<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Moderation\ModerationReason;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Moderation\Flag;

/**
 * Whatever `Manager::moderationTargets()` actually resolves for `Moment` at runtime -- never
 * hardcoded. `reactions` already claims `Moment` under its own `morphAlias('moment')`, and
 * `AggregatesModerationTargets` reuses an already-registered alias rather than minting a
 * competing second one (see that trait's own docblock) -- so the real value here depends on
 * which other extensions happen to be installed, not something a test should assume.
 */
function momentModerationType(): string
{
    return app(Manager::class)->moderationTargets()
        ->first(fn ($target) => $target->model === Moment::class)
        ->alias;
}

function moderationPerson(string $email = 'person@example.test'): Person
{
    return Person::create(['name' => 'Person', 'email' => $email, 'password' => 'secret']);
}

function personWithModeratePermission(): Person
{
    $person = moderationPerson('mod@example.test');

    $group = Group::create(['name' => 'Moderators']);
    $group->givePermissionTo('kopling-moderation::moderate');
    $person->groups()->attach($group);

    return $person;
}

function momentForModeration(Person $author): Moment
{
    return Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
}

it('denies a guest reporting', function () {
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'spam'])
        ->assertRedirect();

    $this->assertGuest();
    expect(Flag::count())->toBe(0);
});

it('a signed-in person can report a moment, creating a pending flag', function () {
    $reporter = moderationPerson('eve@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->actingAs($reporter)
        ->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", [
            'reason' => 'spam',
            'note' => 'Looks like spam',
        ])
        ->assertRedirect();

    $flag = Flag::first();

    expect($flag)->not->toBeNull()
        ->and($flag->flaggable_type)->toBe($type)
        ->and($flag->flaggable_id)->toBe($moment->id)
        ->and($flag->person_id)->toBe($reporter->id)
        ->and($flag->reason)->toBe(ModerationReason::Spam)
        ->and($flag->note)->toBe('Looks like spam')
        ->and($flag->status)->toBe(Flag::STATUS_PENDING);
});

it('re-reporting the same moment by the same person updates the same flag row, not a second one', function () {
    $reporter = moderationPerson('eve@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();

    $this->actingAs($reporter)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'spam']);
    $this->actingAs($reporter)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'illegal']);

    expect(Flag::count())->toBe(1)
        ->and(Flag::first()->reason)->toBe(ModerationReason::Illegal);
});

it('rejects a report for a type that is not a registered moderation target', function () {
    $reporter = moderationPerson('eve@example.test');
    $group = Group::create(['name' => 'X']);

    $this->actingAs($reporter)
        ->post("/_xhr/kopling-moderation/kopling-core-group/{$group->id}", ['reason' => 'spam'])
        ->assertNotFound();
});

it('denies a guest dismissing a flag', function () {
    $reporter = moderationPerson('eve@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();
    $this->actingAs($reporter)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'spam']);
    $flag = Flag::first();

    // The Moderation portal's own route group is gated by "can:kopling-moderation::moderate"
    // directly (not "auth"), so a guest gets a 403 here -- Gate::define()'s Guest substitution
    // always fails hasPermission() -- never a login redirect, unlike the community-attached
    // routes above which only require "auth".
    $this->post("/moderation/flags/{$flag->id}/dismiss")->assertForbidden();

    expect($flag->fresh()->status)->toBe(Flag::STATUS_PENDING);
});

it('denies a person without moderate permission dismissing a flag', function () {
    $person = moderationPerson('nobody@example.test');
    $reporter = moderationPerson('eve@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();
    $this->actingAs($reporter)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'spam']);
    $flag = Flag::first();

    $this->actingAs($person)
        ->post("/moderation/flags/{$flag->id}/dismiss")
        ->assertForbidden();
});

it('dismisses a flag, leaving the moment untouched', function () {
    $moderator = personWithModeratePermission();
    $reporter = moderationPerson('eve@example.test');
    $author = moderationPerson('bob@example.test');
    $moment = momentForModeration($author);
    $type = momentModerationType();
    $this->actingAs($reporter)->post("/_xhr/kopling-moderation/{$type}/{$moment->id}", ['reason' => 'spam']);
    $flag = Flag::first();

    $this->actingAs($moderator)
        ->post("/moderation/flags/{$flag->id}/dismiss")
        ->assertRedirect();

    expect($flag->fresh()->status)->toBe(Flag::STATUS_DISMISSED)
        ->and($flag->fresh()->resolved_by)->toBe($moderator->id)
        ->and($moment->fresh()->trashed())->toBeFalse();
});
