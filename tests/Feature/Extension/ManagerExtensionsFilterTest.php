<?php

declare(strict_types=1);

use Kopling\Core\Settings\EnabledExtensions;

function fakeToggleableManager(): Kopling\Core\Extension\Manager
{
    return fakeManager([
        'tests-fixtures/disableable' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\Disableable\\',
            'path' => __DIR__,
        ],
        'tests-fixtures/pinned' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\Pinned\\',
            'path' => __DIR__,
        ],
    ]);
}

it('includes every extension in both variants before anything has ever been toggled', function () {
    $manager = fakeToggleableManager();

    expect($manager->extensions())->toHaveKey('tests-fixtures/disableable')
        ->and($manager->extensions())->toHaveKey('tests-fixtures/pinned')
        ->and($manager->extensions(includeDisabled: true))->toHaveKey('tests-fixtures/disableable')
        ->and($manager->extensions(includeDisabled: true))->toHaveKey('tests-fixtures/pinned');
});

it('filters a disabled extension out of extensions() but keeps it in extensions(includeDisabled: true)', function () {
    EnabledExtensions::disable('tests-fixtures-disableable', ['tests-fixtures-disableable', 'tests-fixtures-pinned']);

    $manager = fakeToggleableManager();

    expect($manager->extensions())->not->toHaveKey('tests-fixtures/disableable')
        ->and($manager->extensions(includeDisabled: true))->toHaveKey('tests-fixtures/disableable');
});

it('never filters out a CannotBeDisabled extension, even if its id somehow ends up disabled', function () {
    EnabledExtensions::disable('tests-fixtures-pinned', ['tests-fixtures-disableable', 'tests-fixtures-pinned']);

    $manager = fakeToggleableManager();

    expect($manager->extensions())->toHaveKey('tests-fixtures/pinned');
});

it('memoizes extensions(false) and extensions(true) independently on the same Manager instance', function () {
    $manager = fakeToggleableManager();

    $manager->extensions();

    EnabledExtensions::disable('tests-fixtures-disableable', ['tests-fixtures-disableable', 'tests-fixtures-pinned']);

    // Same instance, already-memoized extensions(false) result stays stale within this request
    // -- expected: once() caches per instance/argument combination, not re-evaluated live.
    expect($manager->extensions())->toHaveKey('tests-fixtures/disableable')
        ->and($manager->extensions(includeDisabled: true))->toHaveKey('tests-fixtures/disableable');
});
