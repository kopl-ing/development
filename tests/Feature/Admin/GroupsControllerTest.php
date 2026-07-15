<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

function personWithManagePeopleForGroups(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-core::manage-people');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/groups')->assertForbidden();
});

it('denies a person without manage-people', function () {
    $person = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Just Admin Access']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $person->groups()->attach($group);

    $this->actingAs($person)->get('/admin/groups')->assertForbidden();
});

it('creates a group', function () {
    $operator = personWithManagePeopleForGroups();

    $this->actingAs($operator)
        ->post('/admin/groups', ['name' => 'New Group'])
        ->assertRedirect('/admin/groups');

    expect(Group::where('name', 'New Group')->exists())->toBeTrue();
});

it('renames a group', function () {
    $operator = personWithManagePeopleForGroups();
    $group = Group::create(['name' => 'Old Name']);

    $this->actingAs($operator)
        ->post("/admin/groups/{$group->id}", ['name' => 'New Name'])
        ->assertRedirect('/admin/groups');

    expect($group->refresh()->name)->toBe('New Name');
});

it('deletes a group and cascades its pivot rows', function () {
    $operator = personWithManagePeopleForGroups();
    $group = Group::create(['name' => 'Doomed']);
    $group->givePermissionTo('kopling-example::do-a-thing');

    $person = Person::create(['name' => 'Cleo', 'email' => 'cleo@example.test', 'password' => 'secret']);
    $person->groups()->attach($group);

    $this->actingAs($operator)
        ->post("/admin/groups/{$group->id}/delete")
        ->assertRedirect('/admin/groups');

    expect(Group::find($group->id))->toBeNull()
        ->and(DB::table('group_person')->where('group_id', $group->id)->exists())->toBeFalse()
        ->and(DB::table('group_permission')->where('group_id', $group->id)->exists())->toBeFalse();
});
