<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;

it('aggregates a fixture extension\'s federated-object contributions, keyed by target class', function () {
    $manager = fakeManager([
        'tests-fixtures/federated-object-contributor-fixture' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\FederatedObjectContributorFixture\\',
            'path' => __DIR__,
        ],
    ]);

    $contributions = $manager->federatedObjectContributions();

    expect($contributions)->toHaveKey(Moment::class);
    expect($contributions->get(Moment::class))->toHaveCount(1);

    $moment = (new Moment())->forceFill(['id' => 'fixture-id']);
    $result = $contributions->get(Moment::class)[0]($moment);

    expect($result)->toHaveKey('attachment');
});

it('returns an empty collection when nothing installed implements ExtendsFederatedObjects', function () {
    $manager = fakeManager();

    expect($manager->federatedObjectContributions())->toBeEmpty();
});
