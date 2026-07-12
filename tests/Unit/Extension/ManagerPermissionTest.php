<?php

declare(strict_types=1);

it('prefixes a declared permission\'s id with the owning package id', function () {
    $manager = fakeManager([
        'tests-fixtures/permission-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\PermissionDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    $permission = collect($manager->permissions())->firstWhere('id', 'tests-fixtures-permission-declarer::manage-widgets');

    expect($permission)->not->toBeNull()
        ->and($permission->label)->toBe('Manage widgets');
});
