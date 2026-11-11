<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/*
 * Exercises the real, already-installed `kopling/admin` package directly (no fakeManager()
 * swap) -- unlike SettingsControllerTest, these routes don't depend on which extension declares
 * anything dynamic, so there's nothing to control for (same approach RoutingTest.php already
 * takes for kopling-admin::admin/settings itself).
 */

function personWithManagePeople(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-core::manage-people');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/people')->assertForbidden();
});

it('denies a person without manage-people', function () {
    $person = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Just Admin Access']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $person->groups()->attach($group);

    $this->actingAs($person)->get('/admin/people')->assertForbidden();
});

it('lists people with their current groups', function () {
    $operator = personWithManagePeople();
    $target = Person::create(['name' => 'Cleo', 'email' => 'cleo@example.test', 'password' => 'secret']);
    $group = Group::create(['name' => 'Moderators']);
    $target->groups()->attach($group);

    $this->actingAs($operator)->get('/admin/people')
        ->assertOk()
        ->assertSee('Cleo')
        ->assertSee('Moderators');
});

it('hides people without an email address unless asked for them', function () {
    $operator = personWithManagePeople();
    Person::create(['name' => 'Cleo', 'email' => 'cleo@example.test', 'password' => 'secret']);
    Person::create(['name' => 'Guest Without Email']);

    $this->actingAs($operator)->get('/admin/people')
        ->assertOk()
        ->assertSee('Cleo')
        ->assertDontSee('Guest Without Email');

    $this->actingAs($operator)->get('/admin/people?with_email=0')
        ->assertOk()
        ->assertSee('Cleo')
        ->assertSee('Guest Without Email');
});

it('filters on any of the selected groups', function () {
    $operator = personWithManagePeople();
    $moderators = Group::create(['name' => 'Moderators']);
    $editors = Group::create(['name' => 'Editors']);
    Group::create(['name' => 'Others']);

    Person::create(['name' => 'Mira', 'email' => 'mira@example.test', 'password' => 'secret'])->groups()->attach($moderators);
    Person::create(['name' => 'Emil', 'email' => 'emil@example.test', 'password' => 'secret'])->groups()->attach($editors);
    Person::create(['name' => 'Nora', 'email' => 'nora@example.test', 'password' => 'secret']);

    $this->actingAs($operator)->get('/admin/people?'.http_build_query(['groups' => [$moderators->id, $editors->id]]))
        ->assertOk()
        ->assertSee('Mira')
        ->assertSee('Emil')
        ->assertDontSee('Nora');
});

it('says so when no one matches the filters', function () {
    $operator = personWithManagePeople();
    $empty = Group::create(['name' => 'Empty']);

    $this->actingAs($operator)->get('/admin/people?'.http_build_query(['groups' => [$empty->id]]))
        ->assertOk()
        ->assertSee('No people match these filters.');
});

it('ignores malformed group filters', function () {
    $operator = personWithManagePeople();

    $this->actingAs($operator)->get('/admin/people?groups[a][b]=c&groups[]=unknown')
        ->assertOk()
        ->assertSee('Ada');
});

it('keeps the active filters after changing a person\'s groups', function () {
    $operator = personWithManagePeople();
    $target = Person::create(['name' => 'Cleo', 'email' => 'cleo@example.test', 'password' => 'secret']);

    $this->actingAs($operator)
        ->from('/admin/people?with_email=0')
        ->post("/admin/people/{$target->id}/groups", ['groups' => []])
        ->assertRedirect('/admin/people?with_email=0');
});

it('syncs a person\'s groups, attaching and detaching in one call', function () {
    $operator = personWithManagePeople();
    $target = Person::create(['name' => 'Cleo', 'email' => 'cleo@example.test', 'password' => 'secret']);

    $oldGroup = Group::create(['name' => 'Old']);
    $newGroup = Group::create(['name' => 'New']);
    $target->groups()->attach($oldGroup);

    $this->actingAs($operator)
        ->post("/admin/people/{$target->id}/groups", ['groups' => [$newGroup->id]])
        ->assertRedirect('/admin/people');

    $target->refresh();

    expect($target->groups->pluck('id')->all())->toBe([$newGroup->id]);
});

it('deletes a person with confirmation, taking their posts but keeping the sanctions they issued', function () {
    $admin = personWithManagePeople();
    $moderator = Person::create(['name' => 'Mo', 'email' => 'mo@example.test', 'password' => 'secret']);
    $banned = Person::create(['name' => 'Spammer', 'email' => 'spam@example.test', 'password' => 'secret']);
    $sanction = \Kopling\Core\People\Sanction::issue($banned, ['access_blocked' => true, 'reason' => 'spam'], $moderator);
    $moment = \Kopling\Core\Content\Moment::create(['person_id' => $moderator->id, 'title' => 'Hi', 'body' => 'Hello']);

    $this->actingAs($admin)->get('/admin/people')->assertOk()
        ->assertSee('hx-confirm="Delete Mo, including everything they posted', false)
        ->assertSee('/admin/people/'.$moderator->id.'/delete', false)
        ->assertDontSee('/admin/people/'.$admin->id.'/delete', false);

    $this->actingAs($admin)->post("/admin/people/{$moderator->id}/delete")->assertRedirect();

    expect(Person::find($moderator->id))->toBeNull()
        ->and(\Kopling\Core\Content\Moment::find($moment->id))->toBeNull()
        ->and($sanction->fresh())->not->toBeNull()
        ->and($sanction->fresh()->issued_by)->toBeNull()
        ->and($banned->fresh()->isAccessBlocked())->toBeTrue();
});

it('refuses to delete yourself, or anyone without manage-people', function () {
    $admin = personWithManagePeople();
    $other = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $this->actingAs($admin)->post("/admin/people/{$admin->id}/delete")->assertForbidden();
    $this->actingAs($other)->post("/admin/people/{$admin->id}/delete")->assertForbidden();

    expect(Person::find($admin->id))->not->toBeNull();
});
