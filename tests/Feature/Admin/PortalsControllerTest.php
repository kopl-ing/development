<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\Settings;

function personWithManageSettingsForPortals(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-admin::manage-settings');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/portals')->assertForbidden();
});

it('lists installed portals, including Core\'s own Community', function () {
    $html = $this->actingAs(personWithManageSettingsForPortals())
        ->get('/admin/portals')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('kopling-core::community')
        ->and($html)->toContain('kopling-admin::admin');
});

it('overrides a portal\'s path', function () {
    $this->actingAs(personWithManageSettingsForPortals())
        ->post('/admin/portals', ['id' => 'kopling-core::community', 'path' => 'community'])
        ->assertRedirect('/admin/portals');

    expect(Settings::get('core.portal_path.kopling-core::community'))->toBe('community');
});

it('rejects a path already used by another portal\'s current effective path', function () {
    $this->actingAs(personWithManageSettingsForPortals())
        ->post('/admin/portals', ['id' => 'kopling-core::community', 'path' => 'admin'])
        ->assertSessionHasErrors('path');

    expect(Settings::get('core.portal_path.kopling-core::community'))->toBeNull();
});

it('resets an override back to the declared default', function () {
    Settings::set('core.portal_path.kopling-core::community', 'community');

    $this->actingAs(personWithManageSettingsForPortals())
        ->post('/admin/portals/reset', ['id' => 'kopling-core::community'])
        ->assertRedirect('/admin/portals');

    expect(Settings::get('core.portal_path.kopling-core::community'))->toBeNull();
});
