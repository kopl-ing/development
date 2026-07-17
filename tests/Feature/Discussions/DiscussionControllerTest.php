<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

it('stores a reply with the submitted document and a server-rendered body_html', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $body = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('Great point!')]],
    ]);

    $this->actingAs($replier)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/discussions.reply', $moment->id), ['body' => $body])
        ->assertOk();

    $reply = Reply::first();

    expect($reply)->not->toBeNull()
        ->and($reply->body)->toBe($body)
        ->and($reply->body_html)->toBe('<p>Great point!</p>');
});

it('rejects a document with no actual text as a validation error', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $body = editorDoc([['type' => 'paragraph']]);

    $this->actingAs($replier)
        ->post(route('kopling-core::community/discussions.reply', $moment->id), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect(Reply::count())->toBe(0);
});

it('mounts exactly one editor for a signed-in person, even with a superseding composer installed', function () {
    // reply-dock is a real installed extension in this app and removes discussions' own
    // `default-composer` slot entry (see its Extension::ux()) -- whichever of the two actually
    // renders, there must never be two live editor mount points on the same page (that was the
    // bug: a CSS-hidden form whose TipTap editor still mounted underneath the real one).
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    // Count real mount elements only -- editor.js's own selector string
    // ('[data-tiptap-editor]', inside dock.blade.php's x-data) also contains the substring.
    expect(substr_count($html, '<div data-tiptap-editor'))->toBe(1);
});

it('shows the reply count on the feed but not as a redundant engage link on the discussion page itself', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Nice!')]]]),
        'body_html' => '<p>Nice!</p>',
    ]);

    $feedHtml = $this->actingAs($author)
        ->get(route('kopling-core::community/community'))
        ->assertOk()
        ->getContent();

    expect($feedHtml)->toContain('1 reply');

    $showHtml = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    // "1 reply" still appears once, from the thread's own heading -- just not doubled by the
    // card's engage link, which is exactly what's suppressed on the moment's own discussion page.
    expect(substr_count($showHtml, '1 reply'))->toBe(1);
});

it('shows a login prompt instead of the composer for a guest', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $this->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->assertSee(__('kopling-discussions::messages.login_to_reply'))
        ->assertDontSee('data-tiptap-editor', false);
});
