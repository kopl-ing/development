<?php

declare(strict_types=1);

it('collects one extension\'s authorize() closure, keyed by model class and ability', function () {
    $manager = fakeManager([
        'tests-fixtures/authorizes-models-fixture' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\AuthorizesModelsFixture\\',
            'path' => __DIR__,
        ],
    ]);

    $rules = $manager->authorizationRules('Fixture\\AuthorizableTarget', 'act');

    expect($rules)->toHaveCount(1)
        ->and($rules[0]())->toBeTrue();
});

it('collects every installed extension\'s closure for the same model+ability, not just the first', function () {
    $manager = fakeManager([
        'tests-fixtures/authorizes-models-fixture' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\AuthorizesModelsFixture\\',
            'path' => __DIR__,
        ],
        'tests-fixtures/authorizes-models-fixture-two' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\AuthorizesModelsFixtureTwo\\',
            'path' => __DIR__,
        ],
    ]);

    $rules = $manager->authorizationRules('Fixture\\AuthorizableTarget', 'act');

    expect($rules)->toHaveCount(2)
        ->and(array_map(fn ($rule) => $rule(), $rules))->toBe([true, false]);
});

it('returns an empty array for an ability nobody registered a rule for', function () {
    $manager = fakeManager([
        'tests-fixtures/authorizes-models-fixture' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\AuthorizesModelsFixture\\',
            'path' => __DIR__,
        ],
    ]);

    expect($manager->authorizationRules('Fixture\\AuthorizableTarget', 'other'))->toBe([])
        ->and($manager->authorizationRules('Fixture\\SomeOtherClass', 'act'))->toBe([]);
});

it('returns an empty array when nothing installed implements ExtendsModels', function () {
    $manager = fakeManager();

    expect($manager->authorizationRules('Fixture\\AuthorizableTarget', 'act'))->toBe([]);
});
