<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Pages\Page;

function personWithManagePages(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Site Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-pages::manage-pages');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/pages')->assertForbidden();
});

it('denies a person with access-admin but not manage-pages', function () {
    $person = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Just Admin Access']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $person->groups()->attach($group);

    $this->actingAs($person)->get('/admin/pages')->assertForbidden();
});

it('creates a page', function () {
    $this->actingAs(personWithManagePages())
        ->post('/admin/pages', [
            'title' => 'About',
            'path' => '/about/',
            'published' => '1',
        ])
        ->assertRedirect();

    $page = Page::sole();
    expect($page->title)->toBe('About')
        ->and($page->path)->toBe('about')
        ->and($page->published)->toBeTrue();
});

it('rejects a duplicate path', function () {
    Page::create(['path' => 'about', 'title' => 'About']);

    $this->actingAs(personWithManagePages())
        ->post('/admin/pages', ['title' => 'About Again', 'path' => 'about'])
        ->assertSessionHasErrors('path');

    expect(Page::count())->toBe(1);
});

it('setting a page as index unsets it on every other page', function () {
    $first = Page::create(['path' => 'a', 'title' => 'A', 'is_index' => true]);
    $second = Page::create(['path' => 'b', 'title' => 'B']);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$second->id}", ['title' => 'B', 'path' => 'b', 'is_index' => '1']);

    expect($first->fresh()->is_index)->toBeFalse()
        ->and($second->fresh()->is_index)->toBeTrue();
});

it('deletes a page and cascades its sections', function () {
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $page->sections()->create(['template_id' => pageSectionTemplate()->id, 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePages())
        ->post("/admin/pages/{$page->id}/delete")
        ->assertRedirect('/admin/pages');

    expect(Page::count())->toBe(0)
        ->and(Kopling\Pages\PageSection::count())->toBe(0);
});
