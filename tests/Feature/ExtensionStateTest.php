<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kopling\Core\Extension\Manager;

/**
 * The enable/disable toggle (issue #6): an installed extension is enabled unless the
 * extension_states table names it, `CannotBeDisabled` is honoured no matter what, and a
 * disabled extension contributes nothing to any of Manager's collectors. Runs against the
 * real discovered set (core + the installed extensions), so these also prove the wiring, not
 * just the arithmetic.
 */
function manager(): Manager
{
    return app(Manager::class);
}

// A Manager resolved fresh after RefreshDatabase has created extension_states, so no
// boot-time (table-not-yet-migrated) memo leaks into the assertions.
beforeEach(fn () => app()->forgetInstance(Manager::class));

it('enables every installed extension by default', function () {
    $manager = manager();

    expect(array_keys($manager->extensions()))->toEqual(array_keys($manager->discovered()))
        ->and($manager->isEnabled('kopling/example'))->toBeTrue();
});

it('disables an extension: gone from extensions(), still discovered and persisted', function () {
    $manager = manager();

    $manager->disable('kopling/example');

    expect($manager->isEnabled('kopling/example'))->toBeFalse()
        ->and($manager->extensions())->not->toHaveKey('kopling/example')
        ->and($manager->discovered())->toHaveKey('kopling/example')
        ->and(DB::table('extension_states')->where('extension', 'kopling/example')->exists())->toBeTrue();
});

it('stops a disabled extension contributing its theme', function () {
    $manager = manager();

    expect($manager->themes()->keys())->toContain('kopling-theme-midnight');

    $manager->disable('kopling/theme-midnight');

    expect($manager->themes()->keys())->not->toContain('kopling-theme-midnight');
});

it('stops a disabled extension contributing its permissions', function () {
    $manager = manager();

    expect(collect($manager->permissions())->pluck('id'))->toContain('kopling-discussions::view');

    $manager->disable('kopling/discussions');

    expect(collect($manager->permissions())->pluck('id'))->not->toContain('kopling-discussions::view');
});

it('re-enables a disabled extension', function () {
    $manager = manager();

    $manager->disable('kopling/example');
    expect($manager->isEnabled('kopling/example'))->toBeFalse();

    $manager->enable('kopling/example');

    expect($manager->isEnabled('kopling/example'))->toBeTrue()
        ->and(DB::table('extension_states')->where('extension', 'kopling/example')->exists())->toBeFalse();
});

it('is idempotent: disabling twice keeps one row, enabling twice is a no-op', function () {
    $manager = manager();

    $manager->disable('kopling/example');
    $manager->disable('kopling/example');
    expect(DB::table('extension_states')->where('extension', 'kopling/example')->count())->toBe(1);

    $manager->enable('kopling/example');
    $manager->enable('kopling/example');
    expect($manager->isEnabled('kopling/example'))->toBeTrue();
});

it('refuses to disable a CannotBeDisabled extension', function () {
    $manager = manager();

    expect(fn () => $manager->disable('kopling/core'))->toThrow(InvalidArgumentException::class);
    expect($manager->isEnabled('kopling/core'))->toBeTrue();
});

it('keeps a CannotBeDisabled extension enabled even if the table names it', function () {
    // Defence in depth: a stale or hand-edited row must never be able to take Core offline.
    DB::table('extension_states')->insert(['extension' => 'kopling/core', 'disabled_at' => now()]);
    app()->forgetInstance(Manager::class);

    expect(manager()->isEnabled('kopling/core'))->toBeTrue();
});

it('throws on a package that is not installed', function () {
    $manager = manager();

    expect(fn () => $manager->disable('kopling/nope'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $manager->enable('kopling/nope'))->toThrow(InvalidArgumentException::class);
});

it('resolves a package from its name, id, or short name', function () {
    $manager = manager();

    expect($manager->resolvePackage('kopling/example'))->toBe('kopling/example')
        ->and($manager->resolvePackage('kopling-example'))->toBe('kopling/example')
        ->and($manager->resolvePackage('example'))->toBe('kopling/example')
        ->and($manager->resolvePackage('nope'))->toBeNull();
});

it('stays up when the state table is missing (boot before its own migration ran)', function () {
    $migration = require base_path('k-core/migrations/2026_07_11_000001_create_extension_states_table.php');

    Schema::drop('extension_states');
    app()->forgetInstance(Manager::class);
    $manager = manager();

    expect(fn () => $manager->extensions())->not->toThrow(Throwable::class)
        ->and(array_keys($manager->extensions()))->toEqual(array_keys($manager->discovered()));

    $migration->up();
})->group('boot-safety');
