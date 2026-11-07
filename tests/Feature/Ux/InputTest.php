<?php

declare(strict_types=1);

it('renders min, max and step only when given', function () {
    $bounded = (string) $this->blade('<x-k::form.input :data="$data" />', ['data' => ['name' => 'n', 'type' => 'number', 'min' => 1, 'max' => 30, 'step' => 0.01]]);
    $plain = (string) $this->blade('<x-k::form.input :data="$data" />', ['data' => ['name' => 'n', 'type' => 'number']]);

    expect($bounded)->toContain('min="1"')->toContain('max="30"')->toContain('step="0.01"')
        ->and($plain)->not->toContain('min=')->not->toContain('max=')->not->toContain('step=');
});
