<?php

declare(strict_types=1);

use Kopling\Pages\Page;
use Kopling\Pages\PageSectionTemplate;

it('404s when no page is set as the index and the portal root is requested', function () {
    $this->get('/pages')->assertNotFound();
});

it('shows the page marked as index at the portal root', function () {
    Page::create(['path' => 'home', 'title' => 'Welcome', 'published' => true, 'is_index' => true]);

    $this->get('/pages')->assertOk()->assertSee('Welcome');
});

it('shows a published page by its path, compiling its section template against its slot data', function () {
    $template = PageSectionTemplate::create([
        'name' => 'Greeting',
        'blade_source' => '<p>{{ $title }}</p>',
        'slots' => [['name' => 'title', 'type' => 'string', 'label' => 'Title']],
    ]);
    $page = Page::create(['path' => 'about', 'title' => 'About Us', 'published' => true]);
    $page->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => ['title' => 'Hello there']]);

    $this->get('/pages/about')
        ->assertOk()
        ->assertSee('Hello there');
});

it('404s an unpublished page even by its exact path', function () {
    Page::create(['path' => 'draft', 'title' => 'Draft', 'published' => false]);

    $this->get('/pages/draft')->assertNotFound();
});

it('renders a wysiwyg slot as its pre-rendered, sanitized html', function () {
    $template = PageSectionTemplate::create([
        'name' => 'Rich text',
        'blade_source' => '<div class="prose">{!! $body !!}</div>',
        'slots' => [['name' => 'body', 'type' => 'wysiwyg', 'label' => 'Body']],
    ]);
    $page = Page::create(['path' => 'landing', 'title' => 'Kopling', 'published' => true]);
    $page->sections()->create([
        'template_id' => $template->id,
        'order' => 1,
        'data' => ['body' => ['json' => '{"type":"doc","content":[]}', 'html' => '<p>Real relationships</p>']],
    ]);

    $this->get('/pages/landing')
        ->assertOk()
        ->assertSee('Kopling')
        ->assertSee('Real relationships', false);
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
