<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;
use Kopling\Core\Storage\Drive;
use Kopling\Core\Storage\Resolver;
use Kopling\Core\Storage\StorageMapping;

/*
 * Swaps the real, container-bound Manager for a fakeManager() built from the
 * StorageRequester/ReadOnlyStorageRequester fixtures, same approach
 * tests/Feature/Admin/SettingsControllerTest.php already uses -- Resolver::findRequest() reads
 * Manager::storageDrivers(), so it needs a Manager that actually declares the ids these tests
 * resolve against.
 */
function swapStorageRequesters(): void
{
    app()->instance(Manager::class, fakeManager([
        'tests-fixtures/storage-requester' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\StorageRequester\\',
            'path' => base_path('tests/Fixtures/Extensions/StorageRequester'),
        ],
        'tests-fixtures/read-only-storage-requester' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ReadOnlyStorageRequester\\',
            'path' => base_path('tests/Fixtures/Extensions/ReadOnlyStorageRequester'),
        ],
    ]));
}

function localDrive(array $overrides = []): Drive
{
    return Drive::create(array_merge([
        'name' => 'Test Local Drive',
        'driver' => 'local',
        'settings' => ['root' => sys_get_temp_dir().'/kopling-resolver-test-'.uniqid()],
        'supports_public' => false,
        'supports_signed' => false,
        'writable' => true,
        'enabled' => true,
    ], $overrides));
}

it('throws when nothing declares the requested id', function () {
    swapStorageRequesters();

    expect(fn () => app(Resolver::class)->resolve('kopling-nothing::content'))
        ->toThrow(RuntimeException::class, 'No extension declares a storage request');
});

it('throws when a declared request has no mapping at all', function () {
    swapStorageRequesters();

    expect(fn () => app(Resolver::class)->resolve('tests-fixtures-storage-requester::attachments'))
        ->toThrow(RuntimeException::class, 'is not mapped to an enabled drive');
});

it('throws when the mapped drive is disabled', function () {
    swapStorageRequesters();

    $drive = localDrive(['enabled' => false]);
    StorageMapping::create(['request_id' => 'tests-fixtures-storage-requester::attachments', 'drive_id' => $drive->id]);

    expect(fn () => app(Resolver::class)->resolve('tests-fixtures-storage-requester::attachments'))
        ->toThrow(RuntimeException::class, 'is not mapped to an enabled drive');
});

it('resolves a mapped local drive and round-trips a write/read', function () {
    swapStorageRequesters();

    $drive = localDrive();
    StorageMapping::create(['request_id' => 'tests-fixtures-storage-requester::attachments', 'drive_id' => $drive->id]);

    $disk = app(Resolver::class)->resolve('tests-fixtures-storage-requester::attachments');
    $disk->put('hello.txt', 'world');

    expect($disk->get('hello.txt'))->toBe('world');
});

it('scopes reads/writes to the mapping\'s own prefix', function () {
    swapStorageRequesters();

    $drive = localDrive();
    StorageMapping::create([
        'request_id' => 'tests-fixtures-storage-requester::attachments',
        'drive_id' => $drive->id,
        'prefix' => 'scoped',
    ]);

    $disk = app(Resolver::class)->resolve('tests-fixtures-storage-requester::attachments');
    $disk->put('hello.txt', 'world');

    expect(file_exists($drive->settings['root'].'/scoped/hello.txt'))->toBeTrue();
});

it('enforces a ReadOnly-declared request even against a writable drive', function () {
    swapStorageRequesters();

    $drive = localDrive(['writable' => true]);
    StorageMapping::create(['request_id' => 'tests-fixtures-read-only-storage-requester::content', 'drive_id' => $drive->id]);

    $disk = app(Resolver::class)->resolve('tests-fixtures-read-only-storage-requester::content');

    expect(fn () => $disk->put('hello.txt', 'world'))
        ->toThrow(RuntimeException::class, 'read-only');
});

it('resolves env: prefixed settings values at build time, never persisting the resolved value', function () {
    swapStorageRequesters();

    putenv('KOPLING_TEST_DRIVE_ROOT='.sys_get_temp_dir().'/kopling-resolver-env-test-'.uniqid());

    $drive = Drive::create([
        'name' => 'Env Drive',
        'driver' => 'local',
        'settings' => ['root' => 'env:KOPLING_TEST_DRIVE_ROOT'],
        'writable' => true,
        'enabled' => true,
    ]);
    StorageMapping::create(['request_id' => 'tests-fixtures-storage-requester::attachments', 'drive_id' => $drive->id]);

    $disk = app(Resolver::class)->resolve('tests-fixtures-storage-requester::attachments');
    $disk->put('hello.txt', 'world');

    expect($disk->get('hello.txt'))->toBe('world')
        ->and($drive->fresh()->settings['root'])->toBe('env:KOPLING_TEST_DRIVE_ROOT');

    putenv('KOPLING_TEST_DRIVE_ROOT');
});

it('throws when an env: referenced variable is not set', function () {
    swapStorageRequesters();

    $drive = Drive::create([
        'name' => 'Missing Env Drive',
        'driver' => 'local',
        'settings' => ['root' => 'env:KOPLING_TEST_DRIVE_DOES_NOT_EXIST'],
        'writable' => true,
        'enabled' => true,
    ]);
    StorageMapping::create(['request_id' => 'tests-fixtures-storage-requester::attachments', 'drive_id' => $drive->id]);

    expect(fn () => app(Resolver::class)->resolve('tests-fixtures-storage-requester::attachments'))
        ->toThrow(RuntimeException::class, 'is not set');
});
