<?php

declare(strict_types=1);

use Kopling\Core\Core;

it('declares the community identity and the sign-up settings', function () {
    $fields = collect((new Core)->adminSettings())->keyBy('id');

    expect($fields->keys()->all())->toBe(['community-name', 'community-logo', 'community-description', 'registration-enabled', 'login-path', 'registration-path', 'auth-redirect-path'])
        ->and($fields['login-path']->default)->toBe('login')
        ->and($fields['community-name']->component)->toBe('k::form.input')
        ->and($fields['community-logo']->component)->toBe('k::form.input')
        ->and($fields['community-description']->component)->toBe('k::form.text-area')
        ->and($fields['registration-enabled']->component)->toBe('k::form.toggle')
        ->and($fields['registration-enabled']->default)->toBeTrue()
        ->and($fields['registration-path']->default)->toBe('register')
        ->and($fields['auth-redirect-path']->default)->toBeNull();

    foreach (['community-name', 'community-logo', 'community-description'] as $id) {
        expect($fields[$id]->default)->toBeNull();
    }
});
