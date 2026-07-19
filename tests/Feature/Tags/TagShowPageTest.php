<?php

declare(strict_types=1);

use Kopling\Tags\Tag;

it('shows the tag\'s icon next to its name in the page header', function () {
    Tag::create(['name' => 'Design', 'slug' => 'design-show-header', 'icon' => 'palette', 'color' => '#E8590C']);

    $html = $this->get('/tag/design-show-header')->assertOk()->getContent();

    expect($html)->toContain('badge-lg')
        ->and($html)->toContain('<svg')
        ->and($html)->toContain('Design');
});

it('shows no icon in the header when the tag has none', function () {
    Tag::create(['name' => 'Plain', 'slug' => 'plain-show-header']);

    $html = $this->get('/tag/plain-show-header')->assertOk()->getContent();

    // Scoped to just the <h1> header itself -- the chrome's own theme-switcher icon (this app
    // has more than one real theme installed) also renders an <svg> elsewhere on the page, so
    // checking the whole document for "no svg at all" isn't a safe assertion here.
    preg_match('/<h1.*?<\/h1>/s', $html, $header);

    expect($header)->not->toBeEmpty()
        ->and($header[0])->toContain('Plain')
        ->and($header[0])->not->toContain('<svg');
});
