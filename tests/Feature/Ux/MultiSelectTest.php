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

it('shows a min/max hint when either is given, phrased per which are set', function () {
    $data = ['name' => 'tags', 'label' => 'Tags', 'options' => ['t1' => 'One'], 'min' => 1, 'max' => 3];

    $html = (string) $this->blade('<x-k::form.multi-select :data="$data" />', ['data' => $data]);

    expect($html)->toContain('Select between 1 and 3.');

    $minOnly = (string) $this->blade('<x-k::form.multi-select :data="$data" />', ['data' => ['name' => 'tags', 'label' => 'Tags', 'options' => [], 'min' => 2]]);
    expect($minOnly)->toContain('Select at least 2.');

    $maxOnly = (string) $this->blade('<x-k::form.multi-select :data="$data" />', ['data' => ['name' => 'tags', 'label' => 'Tags', 'options' => [], 'max' => 5]]);
    expect($maxOnly)->toContain('Select up to 5.');
});

it('renders no min/max hint when neither is given', function () {
    $data = ['name' => 'groups', 'label' => 'Groups', 'options' => []];

    $html = (string) $this->blade('<x-k::form.multi-select :data="$data" />', ['data' => $data]);

    expect($html)->not->toContain('Select ');
});

it('renders slot content instead of the default checkbox loop when given', function () {
    $data = ['name' => 'tags', 'label' => 'Tags', 'options' => ['t1' => 'One']];

    $html = (string) $this->blade(
        '<x-k::form.multi-select :data="$data"><div class="custom-tag-option">Custom markup</div></x-k::form.multi-select>',
        ['data' => $data]
    );

    expect($html)->toContain('custom-tag-option')
        ->and($html)->toContain('Custom markup')
        ->and($html)->not->toContain('type="checkbox"');
});
