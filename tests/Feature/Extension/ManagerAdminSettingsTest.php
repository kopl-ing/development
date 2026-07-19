<?php

declare(strict_types=1);

$declarer = [
    'namespace' => 'Tests\\Fixtures\\Extensions\\AdminSettingsDeclarer\\',
    'path' => __DIR__,
];

it('prefixes a declared Field\'s id with the owning package id, grouped by owner', function () use ($declarer) {
    $manager = fakeManager(['tests-fixtures/admin-settings-declarer' => $declarer]);

    $settings = $manager->adminSettings();

    expect($settings->has('tests-fixtures-admin-settings-declarer'))->toBeTrue();

    $field = $settings->get('tests-fixtures-admin-settings-declarer')[0];

    expect($field->id)->toBe('tests-fixtures-admin-settings-declarer::enabled')
        ->and($field->default)->toBeTrue()
        ->and($field->component)->toBe('k::form.toggle');
});

it('does not include an extension that never declared any admin settings', function () use ($declarer) {
    // Core itself now implements HasAdminSettings (community-name/-logo/-description) and is
    // always present regardless of fakeManager()'s fixture list, so it's no longer a valid
    // "declares nothing" example -- `Pinned` (a plain fixture, no HasAdminSettings) is used
    // instead.
    $manager = fakeManager([
        'tests-fixtures/admin-settings-declarer' => $declarer,
        'tests-fixtures/pinned' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\Pinned\\',
            'path' => base_path('tests/Fixtures/Extensions/Pinned'),
        ],
    ]);

    expect($manager->adminSettings()->has('tests-fixtures-admin-settings-declarer'))->toBeTrue()
        ->and($manager->adminSettings()->has('tests-fixtures-pinned'))->toBeFalse();
});
