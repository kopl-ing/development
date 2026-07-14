<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;

/*
 * These exercise the real, currently-installed extension set (Core + kopling/example, etc.),
 * not a fixture -- kopling/example exists specifically to be "one working, verified example of
 * every convention" (see its own docblock), so it doubles as the end-to-end proof that
 * ExtendsPortals really is wired correctly at the HTTP layer: routes actually respond under the
 * right prefix/middleware, and css/js actually get served through the key-based asset route.
 * See tests/Unit/Extension/ManagerPortalTest.php for the aggregation logic itself, tested
 * against disposable fixtures instead.
 */

it('registers an ExtendsPortals-attached route under its target Portal\'s prefix and name', function () {
    $this->get('/_example/hello')
        ->assertOk();

    expect(route('kopling-core::community/example.hello'))->toContain('/_example/hello');
});

it('links a PortalExtension\'s css/js onto a page rendered under that Portal', function () {
    $response = $this->get('/');

    $response->assertOk();

    $manager = app(Manager::class);
    $example = $manager->portalExtensions()->get('kopling-core::community')
        ->first(fn ($extension) => str_contains($extension->routes ?? '', '/k-extensions/example/'));

    expect($example)->not->toBeNull();

    $response->assertSee(Manager::assetUrl($example->css), false);
    $response->assertSee(Manager::assetUrl($example->js), false);
});

it('serves a PortalExtension\'s css file through the key-based asset route with the right content type', function () {
    $manager = app(Manager::class);
    $example = $manager->portalExtensions()->get('kopling-core::community')
        ->first(fn ($extension) => str_contains($extension->routes ?? '', '/k-extensions/example/'));

    $this->get(Manager::assetUrl($example->css))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=utf-8');
});

it('routes kopling/admin\'s own settings page under its own Portal prefix, gated behind its own permission', function () {
    // kopling/admin now attaches its settings route via ExtendsPortals (see its own
    // Extension::extendsPortals()) -- a guest still can't reach it (the Portal's own
    // `can:access-admin` middleware denies before the route's own `manage-settings` gate is
    // ever reached), but the route itself exists and answers under the right prefix.
    expect(route('kopling-admin::admin/settings'))->toContain('/admin/settings');

    $this->get('/admin/settings')->assertForbidden();
});
