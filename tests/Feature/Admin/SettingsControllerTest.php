<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\EnabledExtensions;
use Kopling\Core\Settings\Settings;

/*
 * Swaps the real, container-bound Manager singleton for a fakeManager() built from the
 * AdminSettingsDeclarer fixture (see tests/Pest.php and ManagerAdminSettingsTest.php), the same
 * approach CardControlTest.php already uses -- so this settings page's own permission gate and
 * field-rendering can be exercised without depending on which real extensions happen to declare
 * settings today. The real `kopling/admin` package is included alongside the fixture (not just
 * the fixture alone): `InjectPortal` resolves `$portal` for every "web" request straight off
 * whichever Manager is currently bound, so a fake Manager that doesn't also know about the real
 * `kopling-admin::admin` Portal would leave `$portal` null for these very requests.
 */

function swapAdminSettings(): void
{
    app()->instance(Manager::class, fakeManager([
        'kopling/admin' => [
            'namespace' => 'Kopling\\Admin\\',
            'path' => base_path('k-extensions/admin'),
        ],
        'tests-fixtures/admin-settings-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\AdminSettingsDeclarer\\',
            'path' => __DIR__,
        ],
    ]));
}

function personWithManageSettings(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-admin::manage-settings');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely -- the Portal\'s own access-admin gate, before manage-settings is ever reached', function () {
    swapAdminSettings();

    $this->get('/admin/settings')->assertForbidden();
});

it('renders the fixture\'s declared field with its default value when nothing is persisted yet', function () {
    swapAdminSettings();

    $response = $this->actingAs(personWithManageSettings())->get('/admin/settings');

    $response->assertOk()
        ->assertSee('Admin Settings Declarer Fixture')
        ->assertSee('name="tests-fixtures-admin-settings-declarer::enabled"', false)
        ->assertSee('checked', false);
});

it('persists a submitted value, keyed by the field\'s already-prefixed id', function () {
    swapAdminSettings();

    $this->actingAs(personWithManageSettings())
        ->post('/admin/settings', ['tests-fixtures-admin-settings-declarer::enabled' => '0'])
        ->assertRedirect('/admin/settings');

    expect(Settings::get('tests-fixtures-admin-settings-declarer::enabled'))->toBe('0');
});

it('flips a normal extension\'s enabled state and clears the registration cache', function () {
    swapAdminSettings();
    app(RegistrationCache::class)->write(['permissions' => []]);

    $response = $this->actingAs(personWithManageSettings())
        ->post('/admin/_xhr/kopling-admin/settings/tests-fixtures-admin-settings-declarer/toggle');

    $response->assertOk();

    expect(EnabledExtensions::isEnabled('tests-fixtures-admin-settings-declarer'))->toBeFalse()
        ->and(app(RegistrationCache::class)->has())->toBeFalse();
});

it('toggles a disabled extension back to enabled on a second call', function () {
    swapAdminSettings();
    $person = personWithManageSettings();

    $this->actingAs($person)->post('/admin/_xhr/kopling-admin/settings/tests-fixtures-admin-settings-declarer/toggle');
    expect(EnabledExtensions::isEnabled('tests-fixtures-admin-settings-declarer'))->toBeFalse();

    $this->actingAs($person)->post('/admin/_xhr/kopling-admin/settings/tests-fixtures-admin-settings-declarer/toggle');
    expect(EnabledExtensions::isEnabled('tests-fixtures-admin-settings-declarer'))->toBeTrue();
});

it('refuses to toggle a CannotBeDisabled extension, server-side, regardless of the UI', function () {
    swapAdminSettings();

    $this->actingAs(personWithManageSettings())
        ->post('/admin/_xhr/kopling-admin/settings/kopling-admin/toggle')
        ->assertForbidden();

    expect(EnabledExtensions::isEnabled('kopling-admin'))->toBeTrue();
});

it('404s toggling an id that does not match any installed extension', function () {
    swapAdminSettings();

    $this->actingAs(personWithManageSettings())
        ->post('/admin/_xhr/kopling-admin/settings/not-a-real-extension/toggle')
        ->assertNotFound();
});
