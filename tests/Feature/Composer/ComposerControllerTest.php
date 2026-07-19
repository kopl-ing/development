<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Tags\Tag;

it('stores a moment with the submitted document and a server-rendered body_html', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $body = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('Hello world')]],
    ]);

    $this->actingAs($person)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/compose.store'), [
            'title' => 'My moment',
            'body' => $body,
        ])
        ->assertOk();

    $moment = Moment::first();

    expect($moment)->not->toBeNull()
        ->and($moment->body)->toBe($body)
        ->and($moment->body_html)->toBe('<p>Hello world</p>');
});

it('rejects a document with no actual text as a validation error', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $body = editorDoc([['type' => 'paragraph']]);

    $this->actingAs($person)
        ->post(route('kopling-core::community/compose.store'), ['title' => 'A moment', 'body' => $body])
        ->assertSessionHasErrors('body');

    expect(Moment::count())->toBe(0);
});

it('rejects a moment with no title -- moments.title is NOT NULL at the schema level', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada-notitle@example.test', 'password' => 'secret']);

    $body = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('Missing a title')]],
    ]);

    $this->actingAs($person)
        ->post(route('kopling-core::community/compose.store'), ['body' => $body])
        ->assertSessionHasErrors('title');

    expect(Moment::count())->toBe(0);
});

it('assigns the submitted tags to the new moment', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada-tags@example.test', 'password' => 'secret']);
    $tag = Tag::create(['name' => 'Feature Request', 'slug' => 'feature-request-compose']);

    $body = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('Please add dark mode')]],
    ]);

    $html = $this->actingAs($person)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/compose.store'), [
            'title' => 'Dark mode',
            'body' => $body,
            'tags' => [$tag->id],
        ])
        ->assertOk()
        ->getContent();

    $moment = Moment::first();

    expect($moment->tags()->pluck('tags.id')->all())->toBe([$tag->id]);

    // Regression guard: two `Ux::add()` entries in `tags`' own Extension::ux() once both
    // resolved to the same id (`kopling-tags::tags`), so the second silently overwrote the
    // first's registration and the badge stopped rendering into `card.body` -- even though the
    // pivot row above was written correctly the whole time. Asserting on the DB alone doesn't
    // catch that; the badge has to actually be in the response this same request returns.
    expect($html)->toContain('Feature Request');
});

it('posts a moment with no tags key at all, leaving it untagged', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada-notags@example.test', 'password' => 'secret']);

    $body = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('No tags here')]],
    ]);

    $this->actingAs($person)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/compose.store'), [
            'title' => 'Plain',
            'body' => $body,
        ])
        ->assertOk();

    $moment = Moment::first();

    expect($moment->tags)->toBeEmpty();
});

it('rejects a submitted tag id that does not exist as a real tag', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada-badtag@example.test', 'password' => 'secret']);

    $body = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('Bad tag id')]],
    ]);

    $this->actingAs($person)
        ->post(route('kopling-core::community/compose.store'), [
            'title' => 'A moment',
            'body' => $body,
            'tags' => ['00000000-0000-0000-0000-000000000000'],
        ])
        ->assertSessionHasErrors('tags.0');

    expect(Moment::count())->toBe(0);
});

it('renders the tag picker mount point, wired to the tags search endpoint, on the community feed', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada-picker@example.test', 'password' => 'secret']);
    Tag::create(['name' => 'Bug Report', 'slug' => 'bug-report-compose']);

    // Tagify mounts client-side, so the hidden name="tags[]" inputs it maintains don't exist
    // in server-rendered HTML at all (Pest's test client doesn't execute JS) -- only the mount
    // point + its wiring can be asserted here; a tag's own name only ever appears via a real
    // search request, never rendered inline into the page itself.
    $html = $this->actingAs($person)->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-tag-input')
        ->and($html)->toContain('data-tag-input-hidden')
        ->and($html)->toContain('data-search-url="'.route('kopling-core::community/tags.search').'"');
});

it('returns matching tags, capped at 5, as JSON from the search endpoint', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada-search@example.test', 'password' => 'secret']);
    foreach (['Bug Report', 'Bug Fix', 'Blocker', 'Backend', 'Board', 'Broken'] as $i => $name) {
        Tag::create(['name' => $name, 'slug' => 'search-'.$i]);
    }

    $response = $this->actingAs($person)->get(route('kopling-core::community/tags.search', ['q' => 'B']))->assertOk();

    $results = $response->json();

    expect($results)->toHaveCount(5)
        ->and($results[0])->toHaveKeys(['id', 'label']);
});

it('denies the search endpoint to a guest', function () {
    $this->get(route('kopling-core::community/tags.search'))->assertRedirect();
    $this->assertGuest();
});

it('rejects a document using a node type outside Core\'s own enabled set', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    // taskList is off by Core's own default (see Core::editor()) -- nothing has enabled it here.
    $body = editorDoc([
        ['type' => 'taskList', 'content' => [
            ['type' => 'taskItem', 'content' => [
                ['type' => 'paragraph', 'content' => [editorText('todo')]],
            ]],
        ]],
    ]);

    $this->actingAs($person)
        ->post(route('kopling-core::community/compose.store'), ['title' => 'A moment', 'body' => $body])
        ->assertSessionHasErrors('body');

    expect(Moment::count())->toBe(0);
});
