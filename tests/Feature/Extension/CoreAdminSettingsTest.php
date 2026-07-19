<?php

declare(strict_types=1);

use Kopling\Core\Core;

it('declares community name, logo, and description as plain Input/TextArea fields', function () {
    $fields = (new Core)->adminSettings();

    expect($fields)->toHaveCount(3);

    $ids = collect($fields)->pluck('id')->all();
    expect($ids)->toBe(['community-name', 'community-logo', 'community-description']);

    foreach ($fields as $field) {
        expect($field->default)->toBeNull();
    }

    expect($fields[0]->component)->toBe('k::form.input')
        ->and($fields[1]->component)->toBe('k::form.input')
        ->and($fields[2]->component)->toBe('k::form.text-area');
});
