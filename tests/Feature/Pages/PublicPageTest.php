<?php

declare(strict_types=1);

use Kopling\Pages\Page;

it('404s when no page is set as the index and the portal root is requested', function () {
    $this->get('/pages')->assertNotFound();
});

it('shows the page marked as index at the portal root', function () {
    Page::create(['path' => 'home', 'title' => 'Welcome', 'published' => true, 'is_index' => true]);

    $this->get('/pages')->assertOk()->assertSee('Welcome');
});

it('shows a published page by its path', function () {
    $page = Page::create(['path' => 'about', 'title' => 'About Us', 'published' => true]);
    $page->sections()->create([
        'kind' => 'rich-text',
        'order' => 1,
        'content' => '{"type":"doc","content":[]}',
        'content_html' => '<p>Hello there</p>',
    ]);

    $this->get('/pages/about')
        ->assertOk()
        ->assertSee('Hello there', false);
});

it('404s an unpublished page even by its exact path', function () {
    Page::create(['path' => 'draft', 'title' => 'Draft', 'published' => false]);

    $this->get('/pages/draft')->assertNotFound();
});

it('renders a hero section using the page\'s own title plus the section\'s subtitle/cta', function () {
    $page = Page::create(['path' => 'landing', 'title' => 'Kopling', 'published' => true]);
    $page->sections()->create([
        'kind' => 'hero',
        'order' => 1,
        'data' => ['subtitle' => 'Real relationships', 'cta_label' => 'Get started', 'cta_url' => 'https://example.test'],
    ]);

    $this->get('/pages/landing')
        ->assertOk()
        ->assertSee('Kopling')
        ->assertSee('Real relationships')
        ->assertSee('Get started')
        ->assertSee('https://example.test', false);
});

it('lists published, show_in_nav pages in the topbar, ordered by nav_order', function () {
    Page::create(['path' => 'zeta', 'title' => 'Zeta', 'published' => true, 'show_in_nav' => true, 'nav_order' => 2]);
    Page::create(['path' => 'alpha', 'title' => 'Alpha', 'published' => true, 'show_in_nav' => true, 'nav_order' => 1]);
    Page::create(['path' => 'hidden', 'title' => 'Hidden', 'published' => true, 'show_in_nav' => false]);
    Page::create(['path' => 'index', 'title' => 'Index', 'published' => true, 'is_index' => true]);

    $html = $this->get('/pages')->assertOk()->getContent();

    expect(strpos($html, 'Alpha'))->toBeLessThan(strpos($html, 'Zeta'))
        ->and($html)->not->toContain('>Hidden<');
});
