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
    Tag::create(['name' => 'Requests', 'slug' => 'requests-index', 'upvote_emoji' => '👍']);

    $html = $this->actingAs($operator)->get('/admin/tags')->assertOk()->getContent();

    expect($html)->toContain('Requests')
        ->and($html)->toContain('data-kop-emoji-picker')
        ->and($html)->toContain('👍');
});

it('creates a tag with vote emoji', function () {
    $operator = personWithManageTags();

    $this->actingAs($operator)
        ->post('/admin/tags', [
            '_form' => 'create',
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
            '_form' => 'create',
            'name' => 'Feature Requests',
            'slug' => 'feature-requests',
            'upvote_emoji' => '👍',
            'downvote_emoji' => '👍',
        ])
        ->assertSessionHasErrors('upvote_emoji');

    expect(Tag::where('slug', 'feature-requests')->exists())->toBeFalse();
});

it('allows a tag with only one vote direction configured', function () {
    $operator = personWithManageTags();

    $this->actingAs($operator)
        ->post('/admin/tags', [
            '_form' => 'create',
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
            '_form' => 'edit-'.$tag->id,
            'name' => 'New Name',
            'slug' => 'old',
            'upvote_emoji' => '🔥',
        ])
        ->assertRedirect('/admin/tags');

    expect($tag->refresh()->name)->toBe('New Name')
        ->and($tag->upvote_emoji)->toBe('🔥');
});

it('deletes a tag and cascades its moment_tag rows', function () {
    $operator = personWithManageTags();
    $tag = Tag::create(['name' => 'Doomed', 'slug' => 'doomed']);

    $this->actingAs($operator)
        ->post("/admin/tags/{$tag->id}/delete")
        ->assertRedirect('/admin/tags');

    expect(Tag::find($tag->id))->toBeNull();
});
