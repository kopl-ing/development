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

it('does not include extensions that never declared any admin settings', function () {
    // Core itself doesn't implement HasAdminSettings today.
    expect(fakeManager()->adminSettings()->has('kopling-core'))->toBeFalse();
});
