<?php

declare(strict_types=1);

use Kopling\Pages\Page;
use Kopling\Pages\PageSection;

it('adds a rich-text section, rendering content_html server-side', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $doc = editorDoc([['type' => 'paragraph', 'content' => [editorText('Hello world')]]]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections", ['kind' => 'rich-text', 'content' => $doc])
        ->assertRedirect();

    $section = $page->sections()->sole();
    expect($section->kind)->toBe('rich-text')
        ->and($section->content)->toBe($doc)
        ->and($section->content_html)->toContain('Hello world')
        ->and($section->order)->toBe(1);
});

it('rejects an empty rich-text document', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $doc = editorDoc([['type' => 'paragraph', 'content' => []]]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections", ['kind' => 'rich-text', 'content' => $doc])
        ->assertSessionHasErrors('content');

    expect($page->sections()->count())->toBe(0);
});

it('adds a hero section with subtitle/cta fields', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections", [
            'kind' => 'hero',
            'subtitle' => 'Real relationships',
            'cta_label' => 'Get started',
            'cta_url' => 'https://example.test',
        ])
        ->assertRedirect();

    $section = $page->sections()->sole();
    expect($section->kind)->toBe('hero')
        ->and($section->data)->toBe(['subtitle' => 'Real relationships', 'cta_label' => 'Get started', 'cta_url' => 'https://example.test'])
        ->and($section->content_html)->toBeNull();
});

it('assigns each new section the next order', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $page->sections()->create(['kind' => 'hero', 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections", ['kind' => 'hero', 'subtitle' => 'Two']);

    expect($page->sections()->orderBy('order')->pluck('order')->all())->toBe([1, 2]);
});

it('swaps order with the neighbor above on move up', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $first = $page->sections()->create(['kind' => 'hero', 'order' => 1, 'data' => ['subtitle' => 'first']]);
    $second = $page->sections()->create(['kind' => 'hero', 'order' => 2, 'data' => ['subtitle' => 'second']]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections/{$second->id}/move", ['direction' => 'up'])
        ->assertRedirect();

    expect($first->fresh()->order)->toBe(2)
        ->and($second->fresh()->order)->toBe(1);
});

it('does nothing when moving the first section up', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $only = $page->sections()->create(['kind' => 'hero', 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections/{$only->id}/move", ['direction' => 'up']);

    expect($only->fresh()->order)->toBe(1);
});

it('deletes a section', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $section = $page->sections()->create(['kind' => 'hero', 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections/{$section->id}/delete")
        ->assertRedirect();

    expect(PageSection::count())->toBe(0);
});

it('404s when the section does not belong to the page in the URL', function () {
    $pageA = Page::create(['path' => 'a', 'title' => 'A']);
    $pageB = Page::create(['path' => 'b', 'title' => 'B']);
    $section = $pageB->sections()->create(['kind' => 'hero', 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$pageA->id}/sections/{$section->id}/delete")
        ->assertNotFound();
});
