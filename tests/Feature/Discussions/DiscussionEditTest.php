<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

it("lets the author update a reply's body, re-rendering body_html server-side", function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $reply = Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Original reply')]]]),
        'body_html' => '<p>Original reply</p>',
    ]);

    $newBody = editorDoc([['type' => 'paragraph', 'content' => [editorText('Edited reply')]]]);

    $this->actingAs($replier)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/discussions.reply.update', $reply), ['body' => $newBody])
        ->assertOk();

    $reply->refresh();

    expect($reply->body)->toBe($newBody)
        ->and($reply->body_html)->toBe('<p>Edited reply</p>');
});

it('rejects an update from anyone other than the reply\'s own author', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Carol', 'email' => 'carol@example.test', 'password' => 'secret']);

    $reply = Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Original reply')]]]),
        'body_html' => '<p>Original reply</p>',
    ]);

    $this->actingAs($other)
        ->post(route('kopling-core::community/discussions.reply.update', $reply), [
            'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Hijacked reply')]]]),
        ])
        ->assertForbidden();

    $reply->refresh();

    expect($reply->body_html)->toBe('<p>Original reply</p>');
});

it('denies the edit form to anyone other than the reply\'s own author', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Carol', 'email' => 'carol@example.test', 'password' => 'secret']);

    $reply = Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Original reply')]]]),
        'body_html' => '<p>Original reply</p>',
    ]);

    $this->actingAs($other)
        ->get(route('kopling-core::community/discussions.reply.edit', $reply))
        ->assertForbidden();
});

it('prefills the reply edit form editor with the existing document', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Existing reply')]]]);

    $reply = Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => $body,
        'body_html' => '<p>Existing reply</p>',
    ]);

    $html = $this->actingAs($replier)
        ->get(route('kopling-core::community/discussions.reply.edit', $reply))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(e($body));
});

it('shows an Edit action only to the reply\'s own author', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $reply = Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('A reply')]]]),
        'body_html' => '<p>A reply</p>',
    ]);

    $replierHtml = $this->actingAs($replier)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($replierHtml)->toContain(route('kopling-core::community/discussions.reply.edit', $reply));

    $authorHtml = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($authorHtml)->not->toContain(route('kopling-core::community/discussions.reply.edit', $reply));
});
