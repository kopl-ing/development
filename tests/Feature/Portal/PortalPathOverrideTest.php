<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Settings\Settings;
use Tests\Support\FakeManifest;

it('resolves the declared default path when no override is set', function () {
    expect(app(Manager::class)->portals()->get('kopling-core::community')->path)->toBe('');
});

it('resolves an admin-configured override over the declared default', function () {
    Settings::set('core.portal_path.kopling-core::community', 'community');

    expect(app(Manager::class)->portals()->get('kopling-core::community')->path)->toBe('community')
        ->and(app(Manager::class)->portals()->get('kopling-core::community')->defaultPath)->toBe('');
});

it('overrides via Settings even when a stale path got baked into RegistrationCache', function () {
    // Simulates the real sequence: kopling:extensions:cache ran once, capturing "path" as it
    // was then; an admin later sets an override without ever running that command again. The
    // override must still win -- applyPortalPathOverrides() always resolves from $defaultPath,
    // never trusting whatever "path" happens to already be sitting in the cache.
    $cachePath = sys_get_temp_dir().'/kopling-test-portal-override-'.uniqid().'.php';
    $cache = new RegistrationCache($cachePath);

    $portal = new Portal(id: 'tests-fixtures-portal-owner::demo', label: 'Demo', path: 'fixture-demo', layout: 'not-rendered-in-this-test');
    $cache->write(['portals' => [$portal->toArray()]]);

    Settings::set('core.portal_path.tests-fixtures-portal-owner::demo', 'overridden-path');

    $manager = new Manager(new FakeManifest([]), new Dispatcher(), $cache);

    expect($manager->portals()->get('tests-fixtures-portal-owner::demo')->path)->toBe('overridden-path');

    $cache->clear();
});
