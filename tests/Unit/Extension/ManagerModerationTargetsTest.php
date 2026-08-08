<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Kopling\Core\People\Group;

it('derives a package-prefixed alias, registers the morph map, and computes softDeletable from actual trait usage', function () {
    $manager = fakeManager([
        'tests-fixtures/moderation-targets-fixture' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\ModerationTargetsFixture\\',
            'path' => __DIR__,
        ],
    ]);

    $targets = $manager->moderationTargets();
    $alias = 'tests-fixtures-moderation-targets-fixture-group';

    expect($targets)->toHaveKey($alias)
        ->and($targets->get($alias)->model)->toBe(Group::class)
        ->and($targets->get($alias)->softDeletable)->toBeFalse()
        ->and(Relation::getMorphedModel($alias))->toBe(Group::class);
});

it('returns an empty collection when nothing installed implements RegistersModerationTargets', function () {
    $manager = fakeManager();

    expect($manager->moderationTargets())->toHaveCount(0);
});
