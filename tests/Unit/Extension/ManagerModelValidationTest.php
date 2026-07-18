<?php

declare(strict_types=1);

it('aggregates a fixture extension\'s extra rules and messages, keyed by target class', function () {
    $manager = fakeManager([
        'tests-fixtures/validates-models-fixture' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ValidatesModelsFixture\\',
            'path' => __DIR__,
        ],
    ]);

    $rules = $manager->modelValidationRules();

    expect($rules)->toHaveKey('Fixture\\Target')
        ->and($rules['Fixture\\Target']['rules'])->toHaveKey('widget_color')
        ->and($rules['Fixture\\Target']['rules']['widget_color'])->toBe(['nullable', 'string', 'max:16'])
        ->and($rules['Fixture\\Target']['messages'])->toBe(['widget_color.max' => 'Widget color is too long.']);
});

it('returns an empty array when nothing installed implements ValidatesModels', function () {
    $manager = fakeManager();

    expect($manager->modelValidationRules())->toBe([]);
});
