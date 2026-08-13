<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;
use Kopling\Tags\Tag;

function personForPostingRestriction(string $email): Person
{
    return Person::create(['name' => 'Person', 'email' => $email, 'password' => 'secret']);
}

it('lets anyone post into an unrestricted tag', function () {
    $person = personForPostingRestriction('open@example.test');
    $tag = Tag::create(['name' => 'Open', 'slug' => 'open-tag']);

    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Hello')]]]);

    $this->actingAs($person)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/compose.store'), [
            'title' => 'A moment',
            'body' => $body,
            'tags' => [$tag->id],
        ])
        ->assertOk();

    $moment = Moment::first();
    expect($moment)->not->toBeNull()
        ->and($moment->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('rejects composing into a restricted tag for a person outside every allowed group', function () {
    $person = personForPostingRestriction('outsider@example.test');
    $tag = Tag::create(['name' => 'Restricted', 'slug' => 'restricted-tag', 'restricted' => true]);
    $tag->groups()->attach(Group::create(['name' => 'Allowed Posters']));

    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Hello')]]]);

    $this->actingAs($person)
        ->post(route('kopling-core::community/compose.store'), [
            'title' => 'A moment',
            'body' => $body,
            'tags' => [$tag->id],
        ])
        ->assertForbidden();

    expect(Moment::count())->toBe(0);
});

it('allows composing into a restricted tag for a person in an allowed group', function () {
    $person = personForPostingRestriction('member@example.test');
    $group = Group::create(['name' => 'Allowed Posters']);
    $person->groups()->attach($group);

    $tag = Tag::create(['name' => 'Restricted', 'slug' => 'restricted-tag-member', 'restricted' => true]);
    $tag->groups()->attach($group);

    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Hello')]]]);

    $this->actingAs($person)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/compose.store'), [
            'title' => 'A moment',
            'body' => $body,
            'tags' => [$tag->id],
        ])
        ->assertOk();

    expect(Moment::count())->toBe(1);
});

it('rejects replying to a moment tagged with a restricted tag for a person outside every allowed group', function () {
    $author = personForPostingRestriction('author@example.test');
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $tag = Tag::create(['name' => 'Restricted', 'slug' => 'restricted-tag-reply', 'restricted' => true]);
    $tag->groups()->attach(Group::create(['name' => 'Allowed Posters']));
    $moment->tags()->attach($tag);

    $replier = personForPostingRestriction('replier@example.test');
    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Great point!')]]]);

    $this->actingAs($replier)
        ->post(route('kopling-core::community/discussions.reply', $moment->id), ['body' => $body])
        ->assertForbidden();

    expect(Reply::count())->toBe(0);
});

it('allows replying to a moment tagged with a restricted tag for a person in an allowed group', function () {
    $author = personForPostingRestriction('author2@example.test');
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $group = Group::create(['name' => 'Allowed Repliers']);
    $tag = Tag::create(['name' => 'Restricted', 'slug' => 'restricted-tag-reply-member', 'restricted' => true]);
    $tag->groups()->attach($group);
    $moment->tags()->attach($tag);

    $replier = personForPostingRestriction('replier2@example.test');
    $replier->groups()->attach($group);

    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Great point!')]]]);

    $this->actingAs($replier)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/discussions.reply', $moment->id), ['body' => $body])
        ->assertOk();

    expect(Reply::count())->toBe(1);
});

it('lets anyone reply to a moment with no tags at all', function () {
    $author = personForPostingRestriction('author3@example.test');
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = personForPostingRestriction('replier3@example.test');
    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Great point!')]]]);

    $this->actingAs($replier)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/discussions.reply', $moment->id), ['body' => $body])
        ->assertOk();

    expect(Reply::count())->toBe(1);
});

it('checks post-in-tag and reply-in-tag as independent permissions of a single Tag::isAllowedBy()', function () {
    $tag = Tag::create(['name' => 'Split', 'slug' => 'split-permissions']);
    $person = personForPostingRestriction('split@example.test');

    expect($tag->isAllowedBy($person, 'kopling-tags::post-in-tag'))->toBeTrue()
        ->and($tag->isAllowedBy($person, 'kopling-tags::reply-in-tag'))->toBeTrue();

    Gate::define('kopling-tags::reply-in-tag', fn () => false);

    expect($tag->isAllowedBy($person, 'kopling-tags::post-in-tag'))->toBeTrue()
        ->and($tag->isAllowedBy($person, 'kopling-tags::reply-in-tag'))->toBeFalse();
});

it('rejects replying based on reply-in-tag specifically, independent of post-in-tag, at the HTTP level', function () {
    $author = personForPostingRestriction('split-author@example.test');
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $moment->tags()->attach(Tag::create(['name' => 'Split', 'slug' => 'split-tag-reply']));

    // Denies replying in any tag, everywhere -- proves DiscussionController::reply() really
    // consults `reply-in-tag`, not `post-in-tag` (which stays untouched, still default-true).
    Gate::define('kopling-tags::reply-in-tag', fn () => false);

    $replier = personForPostingRestriction('split-replier@example.test');
    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Nope')]]]);

    $this->actingAs($replier)
        ->post(route('kopling-core::community/discussions.reply', $moment->id), ['body' => $body])
        ->assertForbidden();

    expect(Reply::count())->toBe(0);
});
