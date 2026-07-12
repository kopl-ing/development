<?php

declare(strict_types=1);

it('prefixes a declared storage request\'s id and groups it by the owning package id', function () {
    $manager = fakeManager([
        'tests-fixtures/storage-requester' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\StorageRequester\\',
            'path' => __DIR__,
        ],
    ]);

    $requests = $manager->storageDrivers()['tests-fixtures-storage-requester'];

    expect($requests)->toHaveCount(1)
        ->and($requests[0]->id)->toBe('tests-fixtures-storage-requester::attachments');
});
