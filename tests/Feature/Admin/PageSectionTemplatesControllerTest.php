<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Pages\Page;
use Kopling\Pages\PageSectionTemplate;

function personWithManagePageTemplates(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Template Admins']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-pages::manage-page-templates');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/section-templates')->assertForbidden();
});

it('denies a person with manage-pages but not manage-page-templates', function () {
    $this->actingAs(personWithManagePages())
        ->get('/admin/section-templates')
        ->assertForbidden();
});

it('creates a template with valid slots json', function () {
    $this->actingAs(personWithManagePageTemplates())
        ->post('/admin/section-templates', [
            'name' => 'Hero',
            'blade_source' => '<h1>{{ $title }}</h1>',
            'slots' => json_encode([['name' => 'title', 'type' => 'string', 'label' => 'Title']]),
        ])
        ->assertRedirect();

    $template = PageSectionTemplate::sole();
    expect($template->name)->toBe('Hero')
        ->and($template->slots)->toBe([['name' => 'title', 'type' => 'string', 'label' => 'Title']]);
});

it('rejects malformed slots json', function () {
    $this->actingAs(personWithManagePageTemplates())
        ->post('/admin/section-templates', [
            'name' => 'Hero',
            'blade_source' => '<h1>{{ $title }}</h1>',
            'slots' => '{not json',
        ])
        ->assertSessionHasErrors('slots');

    expect(PageSectionTemplate::count())->toBe(0);
});

it('rejects a slot with an invalid variable-style name', function () {
    $this->actingAs(personWithManagePageTemplates())
        ->post('/admin/section-templates', [
            'name' => 'Hero',
            'blade_source' => '<h1>{{ $title }}</h1>',
            'slots' => json_encode([['name' => 'not a variable', 'type' => 'string', 'label' => 'Title']]),
        ])
        ->assertSessionHasErrors('slots');

    expect(PageSectionTemplate::count())->toBe(0);
});

it('rejects a slot with an unrecognized type', function () {
    $this->actingAs(personWithManagePageTemplates())
        ->post('/admin/section-templates', [
            'name' => 'Hero',
            'blade_source' => '<h1>{{ $title }}</h1>',
            'slots' => json_encode([['name' => 'title', 'type' => 'markdown', 'label' => 'Title']]),
        ])
        ->assertSessionHasErrors('slots');

    expect(PageSectionTemplate::count())->toBe(0);
});

it('updates a template', function () {
    $template = PageSectionTemplate::create([
        'name' => 'Hero',
        'blade_source' => '<h1>{{ $title }}</h1>',
        'slots' => [['name' => 'title', 'type' => 'string', 'label' => 'Title']],
    ]);

    $this->actingAs(personWithManagePageTemplates())
        ->post("/admin/section-templates/{$template->id}", [
            'name' => 'Hero v2',
            'blade_source' => '<h2>{{ $title }}</h2>',
            'slots' => json_encode([['name' => 'title', 'type' => 'string', 'label' => 'Title']]),
        ])
        ->assertRedirect();

    expect($template->fresh()->name)->toBe('Hero v2')
        ->and($template->fresh()->blade_source)->toBe('<h2>{{ $title }}</h2>');
});

it('deletes an unused template', function () {
    $template = PageSectionTemplate::create([
        'name' => 'Hero',
        'blade_source' => '<h1>{{ $title }}</h1>',
        'slots' => [],
    ]);

    $this->actingAs(personWithManagePageTemplates())
        ->post("/admin/section-templates/{$template->id}/delete")
        ->assertRedirect();

    expect(PageSectionTemplate::count())->toBe(0);
});

it('refuses to delete a template still used by a page section', function () {
    $template = PageSectionTemplate::create([
        'name' => 'Hero',
        'blade_source' => '<h1>{{ $title }}</h1>',
        'slots' => [],
    ]);
    $page = Page::create(['path' => 'a', 'title' => 'A']);
    $page->sections()->create(['template_id' => $template->id, 'order' => 1, 'data' => []]);

    $this->actingAs(personWithManagePageTemplates())
        ->post("/admin/section-templates/{$template->id}/delete")
        ->assertSessionHasErrors('template');

    expect(PageSectionTemplate::count())->toBe(1);
});
