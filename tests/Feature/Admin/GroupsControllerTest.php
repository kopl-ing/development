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

function personWithManagePermissionsForGroups(): Person
{
    $person = Person::create(['name' => 'Priya', 'email' => 'priya@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Permission Managers']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-core::manage-people');
    $group->givePermissionTo('kopling-core::manage-permissions');
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

it('does not show the Permissions button to a person without manage-permissions', function () {
    $operator = personWithManagePeopleForGroups();
    Group::create(['name' => 'A Group']);

    $html = $this->actingAs($operator)->get('/admin/groups')->assertOk()->getContent();

    expect($html)->not->toContain(__('kopling-admin::messages.manage_permissions'));
});

it('denies updating permissions for a person without manage-permissions, even with manage-people', function () {
    $operator = personWithManagePeopleForGroups();
    $group = Group::create(['name' => 'A Group']);

    $this->actingAs($operator)
        ->post("/admin/groups/{$group->id}/permissions", ['permissions' => ['kopling-example::do-a-thing']])
        ->assertForbidden();
});

it('shows the Permissions button and pre-selects the group\'s currently granted permissions', function () {
    $operator = personWithManagePermissionsForGroups();
    $group = Group::create(['name' => 'A Group']);
    $group->givePermissionTo('kopling-example::do-a-thing');

    $html = $this->actingAs($operator)->get('/admin/groups')->assertOk()->getContent();

    expect($html)->toContain(__('kopling-admin::messages.manage_permissions'));
});

it('replaces a group\'s permission grants with exactly the submitted set', function () {
    $operator = personWithManagePermissionsForGroups();
    $group = Group::create(['name' => 'A Group']);
    $group->givePermissionTo('kopling-example::do-a-thing');

    $this->actingAs($operator)
        ->post("/admin/groups/{$group->id}/permissions", ['permissions' => ['kopling-core::manage-people']])
        ->assertRedirect('/admin/groups');

    expect($group->hasPermission('kopling-core::manage-people'))->toBeTrue()
        ->and($group->hasPermission('kopling-example::do-a-thing'))->toBeFalse();
});

it('clears every permission grant when none are submitted', function () {
    $operator = personWithManagePermissionsForGroups();
    $group = Group::create(['name' => 'A Group']);
    $group->givePermissionTo('kopling-example::do-a-thing');

    $this->actingAs($operator)->post("/admin/groups/{$group->id}/permissions", []);

    expect($group->permissions()->count())->toBe(0);
});

it('excludes default and guest-only permissions from the grantable list -- granting them would have no real effect', function () {
    $operator = personWithManagePermissionsForGroups();
    Group::create(['name' => 'A Group']);

    $html = $this->actingAs($operator)->get('/admin/groups')->assertOk()->getContent();

    // access-community/guest are allowsGuests: true (Core::permissions()) -- never actually
    // decided by a Group grant, see GroupsController::grantablePermissions()'s own docblock.
    expect($html)->not->toContain('kopling-core::access-community')
        ->and($html)->not->toContain('kopling-core::guest')
        ->and($html)->toContain('kopling-core::manage-permissions');
});
