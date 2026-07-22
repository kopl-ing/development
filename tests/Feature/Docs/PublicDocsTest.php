<?php

declare(strict_types=1);

use Kopling\Docs\DocPage;

it('shows an empty state at the portal root when nothing has been synced yet', function () {
    $this->get('/docs')
        ->assertOk()
        ->assertSee('No docs pages yet', false);
});

it('shows the first page (by order, then title) at the portal root', function () {
    DocPage::create(['slug' => 'z', 'title' => 'Zeta Page', 'section' => 'General', 'order' => 2, 'storage_path' => 'z.md', 'content_hash' => 'x', 'content_html' => '<p>Z</p>']);
    DocPage::create(['slug' => 'a', 'title' => 'Alpha Page', 'section' => 'General', 'order' => 1, 'storage_path' => 'a.md', 'content_hash' => 'y', 'content_html' => '<p>A</p>']);

    $this->get('/docs')->assertOk()->assertSee('Alpha Page');
});

it('shows a page by its (possibly hierarchical) slug', function () {
    DocPage::create(['slug' => 'extending/portals', 'title' => 'Portals', 'section' => 'Extending', 'storage_path' => 'x.md', 'content_hash' => 'x', 'content_html' => '<p>About Portals</p>']);

    $this->get('/docs/extending/portals')
        ->assertOk()
        ->assertSee('Portals')
        ->assertSee('About Portals');
});

it('404s an unknown slug', function () {
    $this->get('/docs/does-not-exist')->assertNotFound();
});

it('renders the sidebar tree grouped by section, with each page linking to its show route', function () {
    DocPage::create(['slug' => 'a', 'title' => 'Page A', 'section' => 'Getting Started', 'order' => 1, 'storage_path' => 'a.md', 'content_hash' => 'x', 'content_html' => '<p>A</p>']);

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('Getting Started')
        ->and($html)->toContain(route('kopling-docs::docs/show', 'a'));
});
