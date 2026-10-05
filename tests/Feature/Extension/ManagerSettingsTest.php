<?php

declare(strict_types=1);

it('prefixes a declared per-person Field\'s id with the owning package id, grouped by owner', function () {
    $manager = fakeManager([
        'tests-fixtures/settings-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\SettingsDeclarer\\',
            'path' => __DIR__,
        ],
        'tests-fixtures/pinned' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\Pinned\\',
            'path' => base_path('tests/Fixtures/Extensions/Pinned'),
        ],
    ]);

    $field = $manager->settings()->get('tests-fixtures-settings-declarer')[0];

    expect($field->id)->toBe('tests-fixtures-settings-declarer::compact')
        ->and($field->component)->toBe('k::form.toggle')
        ->and($manager->settings()->has('tests-fixtures-pinned'))->toBeFalse()
        ->and($manager->adminSettings()->has('tests-fixtures-settings-declarer'))->toBeFalse();
});
