<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\Settings\EnabledExtensions;

/*
 * Reuses the same `Disableable`/`Pinned` fixtures `ManagerExtensionsFilterTest` already
 * established -- a plain, ordinary extension and a `CannotBeDisabled` one -- rather than
 * inventing new ones, so both places agree on exactly what "toggleable" vs. "not" looks like.
 */
function swapExtensionCommandsManager(): void
{
    app()->instance(Manager::class, fakeManager([
        'tests-fixtures/disableable' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\Disableable\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/Disableable',
        ],
        'tests-fixtures/pinned' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\Pinned\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/Pinned',
        ],
    ]));
}

it('list shows package, name, description, and enabled state for every installed extension', function () {
    swapExtensionCommandsManager();

    // Calls Artisan directly and reads its own real, unmocked output buffer rather than
    // `$this->artisan()`'s `expectsOutputToContain()` -- that helper checks each individual
    // `doWrite()` call in isolation, and `$this->table()` can split a single cell's text across
    // more than one such call under the narrow width Console assumes without a real TTY, so a
    // perfectly-present phrase can still fail a per-call substring check.
    Artisan::call('kopling:extensions:list');
    $output = Artisan::output();

    expect($output)->toContain('tests-fixtures/disableable')
        ->toContain('Disableable Fixture')
        ->toContain('tests-fixtures/pinned')
        ->toContain('Pinned Fixture');
});

it('enable turns on a disabled extension and clears the registration cache', function () {
    swapExtensionCommandsManager();
    EnabledExtensions::disable('tests-fixtures-disableable', ['tests-fixtures-disableable', 'tests-fixtures-pinned']);
    app(RegistrationCache::class)->write(['permissions' => []]);

    $this->artisan('kopling:extensions:enable', ['package' => 'tests-fixtures/disableable'])
        ->assertExitCode(0);

    expect(EnabledExtensions::isEnabled('tests-fixtures-disableable'))->toBeTrue()
        ->and(app(RegistrationCache::class)->has())->toBeFalse();
});

it('enable is a no-op success when already enabled', function () {
    swapExtensionCommandsManager();

    $this->artisan('kopling:extensions:enable', ['package' => 'tests-fixtures/disableable'])
        ->assertExitCode(0)
        ->expectsOutputToContain('already enabled');
});

it('disable turns off an enabled extension and clears the registration cache', function () {
    swapExtensionCommandsManager();
    app(RegistrationCache::class)->write(['permissions' => []]);

    $this->artisan('kopling:extensions:disable', ['package' => 'tests-fixtures/disableable'])
        ->assertExitCode(0);

    expect(EnabledExtensions::isEnabled('tests-fixtures-disableable'))->toBeFalse()
        ->and(app(RegistrationCache::class)->has())->toBeFalse();
});

it('disable is a no-op success when already disabled', function () {
    swapExtensionCommandsManager();
    EnabledExtensions::disable('tests-fixtures-disableable', ['tests-fixtures-disableable', 'tests-fixtures-pinned']);

    $this->artisan('kopling:extensions:disable', ['package' => 'tests-fixtures/disableable'])
        ->assertExitCode(0)
        ->expectsOutputToContain('already disabled');
});

it('refuses to disable a CannotBeDisabled extension and leaves it enabled', function () {
    swapExtensionCommandsManager();

    $this->artisan('kopling:extensions:disable', ['package' => 'tests-fixtures/pinned'])
        ->assertExitCode(1);

    expect(EnabledExtensions::isEnabled('tests-fixtures-pinned'))->toBeTrue();
});

it('treats enabling a CannotBeDisabled extension as already-satisfied, not an error', function () {
    swapExtensionCommandsManager();

    $this->artisan('kopling:extensions:enable', ['package' => 'tests-fixtures/pinned'])
        ->assertExitCode(0)
        ->expectsOutputToContain("can't be disabled");
});

it('resolves a package by short name or id, not just the full vendor/name', function () {
    swapExtensionCommandsManager();

    $this->artisan('kopling:extensions:disable', ['package' => 'disableable'])
        ->assertExitCode(0);

    expect(EnabledExtensions::isEnabled('tests-fixtures-disableable'))->toBeFalse();
});

it('fails with a clear error for a package nothing installed matches', function () {
    swapExtensionCommandsManager();

    $this->artisan('kopling:extensions:enable', ['package' => 'not-a-real-package'])
        ->assertExitCode(1)
        ->expectsOutputToContain('No installed extension matches');
});
