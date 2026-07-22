<?php

declare(strict_types=1);

$portalOwner = [
    'namespace' => 'Tests\\Fixtures\\Extensions\\PortalOwner\\',
    'path' => __DIR__,
];

$portalAttacher = [
    'namespace' => 'Tests\\Fixtures\\Extensions\\PortalAttacher\\',
    'path' => __DIR__,
];

it('prefixes a declared Portal\'s id and permission with the owning package id', function () use ($portalOwner) {
    $manager = fakeManager(['tests-fixtures/portal-owner' => $portalOwner]);

    $portal = $manager->portals()->get('tests-fixtures-portal-owner::demo');

    expect($portal)->not->toBeNull()
        ->and($portal->id)->toBe('tests-fixtures-portal-owner::demo')
        ->and($portal->permission)->toBe('tests-fixtures-portal-owner::access-demo');
});

it('keeps $path equal to the declared $defaultPath when nothing overrides it', function () use ($portalOwner) {
    // A bare fakeManager() test boots no app/DB at all, so the Settings::get() lookup inside
    // applyPortalPathOverrides() always hits its own caught RuntimeException path and falls
    // back to $defaultPath -- see tests/Feature/Portal/PortalPathOverrideTest.php for the real,
    // DB-backed override actually taking effect.
    $portal = fakeManager(['tests-fixtures/portal-owner' => $portalOwner])
        ->portals()->get('tests-fixtures-portal-owner::demo');

    expect($portal->path)->toBe('fixture-demo')
        ->and($portal->defaultPath)->toBe('fixture-demo');
});

it('always includes Core\'s own Community Portal regardless of what else is discovered', function () {
    $manager = fakeManager();

    expect($manager->portals()->has('kopling-core::community'))->toBeTrue();
});

it('groups every ExtendsPortals attachment by its target Portal id, across extensions', function () use ($portalOwner, $portalAttacher) {
    $manager = fakeManager([
        'tests-fixtures/portal-owner' => $portalOwner,
        'tests-fixtures/portal-attacher' => $portalAttacher,
    ]);

    $attachments = $manager->portalExtensions()->get('tests-fixtures-portal-owner::demo');

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('routes')->all())->each->toBeString();
});

it('never prefixes a PortalExtension\'s target -- it\'s a foreign reference, same convention as Ux::after()/before()', function () use ($portalOwner) {
    $manager = fakeManager(['tests-fixtures/portal-owner' => $portalOwner]);

    $extension = $manager->portalExtensions()->get('tests-fixtures-portal-owner::demo')->first();

    expect($extension->portal)->toBe('tests-fixtures-portal-owner::demo');
});

it('does not throw when an ExtendsPortals attachment targets a Portal nothing ever declared', function () use ($portalAttacher) {
    $manager = fakeManager(['tests-fixtures/portal-attacher' => $portalAttacher]);

    $attachments = $manager->portalExtensions();

    expect($attachments->has('tests-fixtures-nonexistent::ghost'))->toBeTrue()
        ->and($manager->portals()->has('tests-fixtures-nonexistent::ghost'))->toBeFalse();

    // The dangling reference is stored exactly as declared -- portalExtensions() doesn't know or
    // care whether a matching Portal exists. Graceful degradation happens one layer up, in the
    // route loop (k-core/routes/web.php), which only ever looks attachments up per real,
    // declared Portal, defaulting to an empty collection when nothing attached at all -- see
    // tests/Feature/Portal/RoutingTest.php for that guarantee exercised against the real app.
});
