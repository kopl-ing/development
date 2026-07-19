<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Tags\Tag;

function personWithManageTags(): Person
{
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Tag Editors']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $group->givePermissionTo('kopling-tags::manage-tags');
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/admin/tags')->assertForbidden();
});

it('denies a person without manage-tags', function () {
    $person = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $group = Group::create(['name' => 'Just Admin Access']);
    $group->givePermissionTo('kopling-admin::access-admin');
    $person->groups()->attach($group);

    $this->actingAs($person)->get('/admin/tags')->assertForbidden();
});

it('renders the index page, including an emoji-picker field for an existing tag', function () {
    $operator = personWithManageTags();
    Tag::forceCreate(['name' => 'Requests', 'slug' => 'requests-index', 'upvote_emoji' => '👍']);

    $html = $this->actingAs($operator)->get('/admin/tags')->assertOk()->getContent();

    expect($html)->toContain('Requests')
        ->and($html)->toContain('data-kop-emoji-picker')
        ->and($html)->toContain('👍');
});

it('creates a tag with vote emoji', function () {
    $operator = personWithManageTags();

    $this->actingAs($operator)
        ->post('/admin/tags', [
            '_form' => 'modal-tag-create',
            'name' => 'Feature Requests',
            'slug' => 'feature-requests',
            'upvote_emoji' => '👍',
            'downvote_emoji' => '👎',
        ])
        ->assertRedirect('/admin/tags');

    $tag = Tag::where('slug', 'feature-requests')->firstOrFail();
    expect($tag->upvote_emoji)->toBe('👍')
        ->and($tag->downvote_emoji)->toBe('👎');
});

it('rejects a tag whose upvote and downvote emoji are the same', function () {
    $operator = personWithManageTags();

    $this->actingAs($operator)
        ->post('/admin/tags', [
            '_form' => 'modal-tag-create',
            'name' => 'Feature Requests',
            'slug' => 'feature-requests',
            'upvote_emoji' => '👍',
            'downvote_emoji' => '👍',
        ])
        ->assertSessionHasErrors('upvote_emoji');

    expect(Tag::where('slug', 'feature-requests')->exists())->toBeFalse();
});

it('reopens the exact modal that failed validation, not the other one, on the follow-up page load', function () {
    $operator = personWithManageTags();
    $tag = Tag::create(['name' => 'Old', 'slug' => 'old']);

    // No assertion chained on this response -- Illuminate\Testing\TestResponse::
    // assertSessionHasErrors() has a side effect that clears the errors flash before a
    // follow-up request can read it (old('_form')/`_old_input` survives regardless; only
    // `session('errors')` does not) -- the reopen script actually appearing below is itself
    // the stronger proof that validation failed and flashed correctly.
    $this->actingAs($operator)->post("/admin/tags/{$tag->id}", [
        '_form' => 'modal-tag-edit-'.$tag->id,
        'name' => '', // required, so this fails
        'slug' => 'old',
    ]);

    $html = $this->actingAs($operator)->get('/admin/tags')->assertOk()->getContent();

    expect($html)->toContain('modal-tag-edit-'.$tag->id.'")?.showModal()')
        ->and($html)->not->toContain('modal-tag-create")?.showModal()');
});

it('allows a tag with only one vote direction configured', function () {
    $operator = personWithManageTags();

    $this->actingAs($operator)
        ->post('/admin/tags', [
            '_form' => 'modal-tag-create',
            'name' => 'Feature Requests',
            'slug' => 'feature-requests',
            'upvote_emoji' => '👍',
        ])
        ->assertSessionDoesntHaveErrors();
});

it('updates a tag', function () {
    $operator = personWithManageTags();
    $tag = Tag::create(['name' => 'Old', 'slug' => 'old']);

    $this->actingAs($operator)
        ->post("/admin/tags/{$tag->id}", [
            '_form' => 'modal-tag-edit-'.$tag->id,
            'name' => 'New Name',
            'slug' => 'old',
            'upvote_emoji' => '🔥',
        ])
        ->assertRedirect('/admin/tags');

    expect($tag->refresh()->name)->toBe('New Name')
        ->and($tag->upvote_emoji)->toBe('🔥');
});

it('renders an icon-picker field, showing the existing tag\'s icon svg', function () {
    $operator = personWithManageTags();
    Tag::create(['name' => 'Design', 'slug' => 'design-icon-test', 'icon' => 'palette']);

    $html = $this->actingAs($operator)->get('/admin/tags')->assertOk()->getContent();

    expect($html)->toContain('data-kop-icon-picker')
        ->and($html)->toContain('<svg');
});

it('creates a tag with an icon and description', function () {
    $operator = personWithManageTags();

    $this->actingAs($operator)
        ->post('/admin/tags', [
            '_form' => 'modal-tag-create',
            'name' => 'Design',
            'slug' => 'design',
            'icon' => 'palette',
            'description' => 'Anything about visual design.',
        ])
        ->assertRedirect('/admin/tags');

    $tag = Tag::where('slug', 'design')->firstOrFail();
    expect($tag->icon)->toBe('palette')
        ->and($tag->description)->toBe('Anything about visual design.');
});

it('updates a tag\'s icon and description', function () {
    $operator = personWithManageTags();
    $tag = Tag::create(['name' => 'Old', 'slug' => 'old-icon-test']);

    $this->actingAs($operator)
        ->post("/admin/tags/{$tag->id}", [
            '_form' => 'modal-tag-edit-'.$tag->id,
            'name' => 'Old',
            'slug' => 'old-icon-test',
            'icon' => 'star',
            'description' => 'Updated description.',
        ])
        ->assertRedirect('/admin/tags');

    expect($tag->refresh()->icon)->toBe('star')
        ->and($tag->description)->toBe('Updated description.');
});

it('deletes a tag and cascades its moment_tag rows', function () {
    $operator = personWithManageTags();
    $tag = Tag::create(['name' => 'Doomed', 'slug' => 'doomed']);

    $this->actingAs($operator)
        ->post("/admin/tags/{$tag->id}/delete")
        ->assertRedirect('/admin/tags');

    expect(Tag::find($tag->id))->toBeNull();
});
