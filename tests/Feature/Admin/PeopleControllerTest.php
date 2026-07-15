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
