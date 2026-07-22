<?php

declare(strict_types=1);

use Kopling\Pages\Page;
use Kopling\Pages\PageSection;

it('adds a section, storing plain slots directly and wysiwyg slots as json+rendered html', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $template = pageSectionTemplate();
    $doc = editorDoc([['type' => 'paragraph', 'content' => [editorText('Hello world')]]]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections", [
            'template_id' => $template->id,
            'title' => 'Welcome',
            'content' => $doc,
        ])
        ->assertRedirect();

    $section = $page->sections()->sole();
    expect($section->template_id)->toBe($template->id)
        ->and($section->data['title'])->toBe('Welcome')
        ->and($section->data['content']['json'])->toBe($doc)
        ->and($section->data['content']['html'])->toContain('Hello world')
        ->and($section->order)->toBe(1);
});

it('rejects an empty wysiwyg document for a wysiwyg slot', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $template = pageSectionTemplate();
    $doc = editorDoc([['type' => 'paragraph', 'content' => []]]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections", [
            'template_id' => $template->id,
            'title' => 'Welcome',
            'content' => $doc,
        ])
        ->assertSessionHasErrors('content');

    expect($page->sections()->count())->toBe(0);
});

it('assigns each new section the next order', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $template = pageSectionTemplate();
    $page->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections", ['template_id' => $template->id, 'title' => 'Two']);

    expect($page->sections()->orderBy('order')->pluck('order')->all())->toBe([1, 2]);
});

it('updates a section using its own template\'s slots', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $template = pageSectionTemplate();
    $section = $page->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => ['title' => 'Old']]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections/{$section->id}", ['title' => 'New'])
        ->assertRedirect();

    expect($section->fresh()->data['title'])->toBe('New');
});

it('swaps order with the neighbor above on move up', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $template = pageSectionTemplate();
    $first = $page->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => ['title' => 'first']]);
    $second = $page->sections()->create(['template_id' => $template->id, 'order' => 2, 'data' => ['title' => 'second']]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections/{$second->id}/move", ['direction' => 'up'])
        ->assertRedirect();

    expect($first->fresh()->order)->toBe(2)
        ->and($second->fresh()->order)->toBe(1);
});

it('does nothing when moving the first section up', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $template = pageSectionTemplate();
    $only = $page->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections/{$only->id}/move", ['direction' => 'up']);

    expect($only->fresh()->order)->toBe(1);
});

it('deletes a section', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $template = pageSectionTemplate();
    $section = $page->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/sections/{$section->id}/delete")
        ->assertRedirect();

    expect(PageSection::count())->toBe(0);
});

it('404s when the section does not belong to the page in the URL', function () {
    $pageA = Page::create(['path' => 'a', 'title' => 'A']);
    $pageB = Page::create(['path' => 'b', 'title' => 'B']);
    $template = pageSectionTemplate();
    $section = $pageB->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$pageA->id}/sections/{$section->id}/delete")
        ->assertNotFound();
});
