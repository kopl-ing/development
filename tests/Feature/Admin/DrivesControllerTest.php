<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Core\Storage\Drive;
use Kopling\Core\Storage\StorageMapping;

function personWithManageSettingsForStorage(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-admin::manage-settings');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/drives')->assertForbidden();
});

it('denies a person without manage-settings', function () {
    $person = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Just Admin Access']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $person->groups()->attach($group);

    $this->actingAs($person)->get('/admin/drives')->assertForbidden();
});

it('lists registered drives', function () {
    Drive::create(['name' => 'Local Disk', 'driver' => 'local', 'settings' => ['root' => '/tmp/x']]);

    $html = $this->actingAs(personWithManageSettingsForStorage())
        ->get('/admin/drives')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Local Disk');
});

it('creates a drive', function () {
    $this->actingAs(personWithManageSettingsForStorage())
        ->post('/admin/drives', [
            'name' => 'Local Disk',
            'driver' => 'local',
            'settings' => json_encode(['root' => '/tmp/x']),
            'writable' => '1',
        ])
        ->assertRedirect('/admin/drives');

    $drive = Drive::sole();
    expect($drive->name)->toBe('Local Disk')
        ->and($drive->driver)->toBe('local')
        ->and($drive->settings)->toBe(['root' => '/tmp/x'])
        ->and($drive->writable)->toBeTrue()
        ->and($drive->enabled)->toBeTrue();
});

it('rejects settings that are not valid JSON', function () {
    $this->actingAs(personWithManageSettingsForStorage())
        ->post('/admin/drives', [
            'name' => 'Broken',
            'driver' => 'local',
            'settings' => '{not json',
        ])
        ->assertSessionHasErrors('settings');

    expect(Drive::count())->toBe(0);
});

it('rejects unescaped backslashes in a pasted Windows-style path, with a specific error', function () {
    $response = $this->actingAs(personWithManageSettingsForStorage())
        ->post('/admin/drives', [
            'name' => 'Broken Path',
            'driver' => 'local',
            'settings' => '{"root": "\\home\\luceos\\code\\kopling\\public\\assets"}',
        ]);

    $response->assertSessionHasErrors('settings');
    expect(session('errors')->first('settings'))->toContain('Syntax error');
    expect(Drive::count())->toBe(0);
});

it('updates a drive', function () {
    $drive = Drive::create(['name' => 'Old Name', 'driver' => 'local', 'settings' => ['root' => '/tmp/x']]);

    $this->actingAs(personWithManageSettingsForStorage())
        ->post("/admin/drives/{$drive->id}", [
            'name' => 'New Name',
            'driver' => 'local',
            'settings' => json_encode(['root' => '/tmp/y']),
            'enabled' => '0',
        ])
        ->assertRedirect('/admin/drives');

    expect($drive->fresh()->name)->toBe('New Name')
        ->and($drive->fresh()->enabled)->toBeFalse();
});

it('deletes an unused drive', function () {
    $drive = Drive::create(['name' => 'Unused', 'driver' => 'local', 'settings' => []]);

    $this->actingAs(personWithManageSettingsForStorage())
        ->post("/admin/drives/{$drive->id}/delete")
        ->assertRedirect('/admin/drives');

    expect(Drive::count())->toBe(0);
});

it('refuses to delete a drive still mapped to a storage request', function () {
    $drive = Drive::create(['name' => 'In Use', 'driver' => 'local', 'settings' => []]);
    StorageMapping::create(['request_id' => 'kopling-example::attachments', 'drive_id' => $drive->id]);

    $this->actingAs(personWithManageSettingsForStorage())
        ->post("/admin/drives/{$drive->id}/delete")
        ->assertSessionHasErrors('drive');

    expect(Drive::count())->toBe(1);
});
