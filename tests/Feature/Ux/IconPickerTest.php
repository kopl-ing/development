<?php

declare(strict_types=1);

it('renders a trigger showing the current value\'s SVG and a hidden input carrying its id', function () {
    $data = ['name' => 'icon', 'label' => 'Icon', 'value' => 'star'];

    $html = (string) $this->blade('<x-k::form.icon-picker :data="$data" />', ['data' => $data]);

    expect($html)->toContain('name="icon"')
        ->and($html)->toContain('value="star"')
        ->and($html)->toContain('<svg')
        ->and($html)->toContain('data-icon-trigger')
        ->and($html)->toContain('data-icon-input');

    preg_match('/data-icon-clear[^>]*hidden/', $html, $hiddenClear);
    expect($hiddenClear)->toBeEmpty();
});

it('shows a plus placeholder and hides the clear button when no value is set', function () {
    $data = ['name' => 'icon', 'label' => 'Icon'];

    $html = (string) $this->blade('<x-k::form.icon-picker :data="$data" />', ['data' => $data]);

    expect($html)->not->toContain('<svg')
        ->and($html)->toContain('＋');

    preg_match('/data-icon-clear[^>]*hidden/', $html, $hiddenClear);
    expect($hiddenClear)->not->toBeEmpty();
});

it('silently renders no icon for a value that does not resolve to a real Font Awesome solid icon', function () {
    $data = ['name' => 'icon', 'label' => 'Icon', 'value' => 'not-a-real-icon-name'];

    $html = (string) $this->blade('<x-k::form.icon-picker :data="$data" />', ['data' => $data]);

    expect($html)->toContain('value="not-a-real-icon-name"')
        ->and($html)->not->toContain('<svg');
});

it('defaults searchUrl to Core\'s own shared icon-search route, not a caller-supplied one', function () {
    $data = ['name' => 'icon', 'label' => 'Icon'];

    $html = (string) $this->blade('<x-k::form.icon-picker :data="$data" />', ['data' => $data]);

    expect($html)->toContain('data-search-url="'.route('kopling-core::community/icon-search').'"');
});

it('renders a description when given', function () {
    $data = ['name' => 'icon', 'label' => 'Icon', 'description' => 'Shown next to the tag name.'];

    $html = (string) $this->blade('<x-k::form.icon-picker :data="$data" />', ['data' => $data]);

    expect($html)->toContain('Shown next to the tag name.');
});
