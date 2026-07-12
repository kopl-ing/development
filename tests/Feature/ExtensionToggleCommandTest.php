<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;

// Each command resolves the shared Manager singleton; forget it per test so a stale enabled
// set from app boot (state table not yet migrated) doesn't leak in.
beforeEach(fn () => app()->forgetInstance(Manager::class));

it('disables then re-enables an extension by short name', function () {
    $this->artisan('kopling:extensions:disable', ['extension' => 'example'])->assertSuccessful();
    expect(app(Manager::class)->isEnabled('kopling/example'))->toBeFalse();

    $this->artisan('kopling:extensions:enable', ['extension' => 'example'])->assertSuccessful();
    expect(app(Manager::class)->isEnabled('kopling/example'))->toBeTrue();
});

it('reports an already-disabled extension without erroring', function () {
    app(Manager::class)->disable('kopling/example');

    $this->artisan('kopling:extensions:disable', ['extension' => 'example'])->assertSuccessful();
});

it('fails to disable a CannotBeDisabled extension', function () {
    $this->artisan('kopling:extensions:disable', ['extension' => 'core'])->assertFailed();

    expect(app(Manager::class)->isEnabled('kopling/core'))->toBeTrue();
});

it('fails on an extension that is not installed', function () {
    $this->artisan('kopling:extensions:disable', ['extension' => 'nope'])->assertFailed();
    $this->artisan('kopling:extensions:enable', ['extension' => 'nope'])->assertFailed();
});

it('lists installed extensions', function () {
    $this->artisan('kopling:extensions:list')->assertSuccessful();
});
