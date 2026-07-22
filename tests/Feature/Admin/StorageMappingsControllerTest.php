<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Core\Storage\Drive;
use Kopling\Core\Storage\StorageMapping;

/*
 * Exercises against `kopling/example`'s own real, already-declared `avatars` StorageRequest
 * (access: Public, permission: ReadWrite -- see k-extensions/example/src/Extension.php) rather
 * than swapping in a fixture Manager -- no isolation concern here the way ResolverTest's unit-ish
 * tests have, and it exercises the real installed system end to end.
 */
function personWithManageSettingsForMappings(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-admin::manage-settings');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/storage')->assertForbidden();
});

it('lists a declared storage request and only its capability-eligible drives', function () {
    $publicWritable = Drive::create(['name' => 'Public Drive', 'driver' => 'local', 'settings' => [], 'supports_public' => true, 'writable' => true]);
    $privateOnly = Drive::create(['name' => 'Private Drive', 'driver' => 'local', 'settings' => [], 'supports_public' => false, 'writable' => true]);
    $readOnly = Drive::create(['name' => 'Read Only Drive', 'driver' => 'local', 'settings' => [], 'supports_public' => true, 'writable' => false]);

    $html = $this->actingAs(personWithManageSettingsForMappings())
        ->get('/admin/storage')
        ->assertOk()
        ->getContent();

    // Scoped to just the "avatars" row's own <select> -- Public Drive/Private Drive/Read Only
    // Drive aren't uniquely eligible/ineligible across the whole page once more than one
    // extension declares a storage request (kopling/docs's own "content" request, Private/
    // ReadOnly, has no restriction and legitimately lists every drive here, Private Drive
    // included).
    $start = strpos($html, 'kopling-example::avatars');
    $rowEnd = strpos($html, '</tr>', $start);
    $row = substr($html, $start, $rowEnd - $start);

    expect($row)->toContain('Public Drive')
        ->not->toContain('Private Drive')
        ->not->toContain('Read Only Drive');
});

it('maps a declared request to an eligible drive', function () {
    $drive = Drive::create(['name' => 'Public Drive', 'driver' => 'local', 'settings' => [], 'supports_public' => true, 'writable' => true]);

    $this->actingAs(personWithManageSettingsForMappings())
        ->post('/admin/storage', [
            'request_id' => 'kopling-example::avatars',
            'drive_id' => $drive->id,
            'prefix' => 'avatars',
        ])
        ->assertRedirect('/admin/storage');

    $mapping = StorageMapping::find('kopling-example::avatars');
    expect($mapping)->not->toBeNull()
        ->and($mapping->drive_id)->toBe($drive->id)
        ->and($mapping->prefix)->toBe('avatars');
});

it('unmaps a request', function () {
    $drive = Drive::create(['name' => 'Public Drive', 'driver' => 'local', 'settings' => [], 'supports_public' => true, 'writable' => true]);
    StorageMapping::create(['request_id' => 'kopling-example::avatars', 'drive_id' => $drive->id]);

    $this->actingAs(personWithManageSettingsForMappings())
        ->post('/admin/storage/delete', ['request_id' => 'kopling-example::avatars'])
        ->assertRedirect('/admin/storage');

    expect(StorageMapping::find('kopling-example::avatars'))->toBeNull();
});

it('surfaces a mapping whose request is no longer declared by any installed extension', function () {
    $drive = Drive::create(['name' => 'Orphan Drive', 'driver' => 'local', 'settings' => []]);
    StorageMapping::create(['request_id' => 'kopling-uninstalled::ghost', 'drive_id' => $drive->id]);

    $html = $this->actingAs(personWithManageSettingsForMappings())
        ->get('/admin/storage')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('kopling-uninstalled::ghost')
        ->toContain('Orphan Drive');
});
