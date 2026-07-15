<?php

declare(strict_types=1);

it('renders one checkbox per option, checking those in value', function () {
    $data = [
        'name' => 'groups',
        'label' => 'Groups',
        'options' => ['g1' => 'Site Admins', 'g2' => 'Moderators'],
        'value' => ['g2'],
    ];

    $html = (string) $this->blade('<x-k::form.multi-select :data="$data" />', ['data' => $data]);

    expect($html)->toContain('name="groups[]"')
        ->and($html)->toContain('value="g1"')
        ->and($html)->toContain('value="g2"')
        ->and($html)->toContain('Site Admins')
        ->and($html)->toContain('Moderators');

    preg_match('/value="g2"[^>]*checked/', $html, $checkedG2);
    preg_match('/value="g1"[^>]*checked/', $html, $checkedG1);

    expect($checkedG2)->not->toBeEmpty()
        ->and($checkedG1)->toBeEmpty();
});

it('falls back to default when no value is given', function () {
    $data = [
        'name' => 'groups',
        'label' => 'Groups',
        'options' => ['g1' => 'Site Admins'],
        'default' => ['g1'],
    ];

    $html = (string) $this->blade('<x-k::form.multi-select :data="$data" />', ['data' => $data]);

    preg_match('/value="g1"[^>]*checked/', $html, $checkedG1);

    expect($checkedG1)->not->toBeEmpty();
});

it('shows a fallback message when there are no options', function () {
    $data = ['name' => 'groups', 'label' => 'Groups', 'options' => []];

    $html = (string) $this->blade('<x-k::form.multi-select :data="$data" />', ['data' => $data]);

    expect($html)->toContain('No options available.')
        ->and($html)->not->toContain('type="checkbox"');
});
