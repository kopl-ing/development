<?php

declare(strict_types=1);

it('renders the mount point wired to the search url, with initial values encoded for JS to read', function () {
    $data = [
        'name' => 'tags',
        'label' => 'Tags',
        'searchUrl' => '/_xhr/kopling-tags/search',
        'value' => [
            ['id' => 'abc', 'label' => 'Bug Report'],
            ['id' => 'def', 'label' => 'Feature Request'],
        ],
    ];

    $html = (string) $this->blade('<x-k::form.tag-input :data="$data" />', ['data' => $data]);

    preg_match('/data-initial-value="([^"]+)"/', $html, $match);
    $decoded = json_decode(html_entity_decode($match[1] ?? ''), true);

    expect($html)->toContain('data-tag-input')
        ->and($html)->toContain('data-search-url="/_xhr/kopling-tags/search"')
        ->and($html)->toContain('data-name="tags"')
        ->and($html)->toContain('data-tag-input-field')
        ->and($html)->toContain('data-tag-input-hidden')
        ->and($decoded)->toBe([
            ['id' => 'abc', 'label' => 'Bug Report'],
            ['id' => 'def', 'label' => 'Feature Request'],
        ]);
});

it('renders an empty initial value array when nothing is selected yet', function () {
    $data = ['name' => 'tags', 'label' => 'Tags', 'searchUrl' => '/_xhr/kopling-tags/search'];

    $html = (string) $this->blade('<x-k::form.tag-input :data="$data" />', ['data' => $data]);

    expect($html)->toContain('data-initial-value="[]"');
});

it('only renders data-max when a max is actually given', function () {
    $withMax = (string) $this->blade('<x-k::form.tag-input :data="$data" />', [
        'data' => ['name' => 'tags', 'label' => 'Tags', 'searchUrl' => '/_xhr/kopling-tags/search', 'max' => 3],
    ]);
    $withoutMax = (string) $this->blade('<x-k::form.tag-input :data="$data" />', [
        'data' => ['name' => 'tags', 'label' => 'Tags', 'searchUrl' => '/_xhr/kopling-tags/search'],
    ]);

    expect($withMax)->toContain('data-max="3"')
        ->and($withoutMax)->not->toContain('data-max');
});

it('shows a min/max hint when given', function () {
    $data = ['name' => 'tags', 'label' => 'Tags', 'searchUrl' => '/_xhr/kopling-tags/search', 'min' => 1, 'max' => 3];

    $html = (string) $this->blade('<x-k::form.tag-input :data="$data" />', ['data' => $data]);

    expect($html)->toContain('Select between 1 and 3.');
});

it('falls back to the generic search placeholder when none is given', function () {
    $data = ['name' => 'tags', 'label' => 'Tags', 'searchUrl' => '/_xhr/kopling-tags/search'];

    $html = (string) $this->blade('<x-k::form.tag-input :data="$data" />', ['data' => $data]);

    expect($html)->toContain('placeholder="Search…"');
});
