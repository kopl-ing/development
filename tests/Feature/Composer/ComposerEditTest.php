<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Poll\Poll;

it("lets the author update a moment's title and body, re-rendering body_html server-side", function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'Original title',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Original body')]]]),
        'body_html' => '<p>Original body</p>',
    ]);

    $newBody = editorDoc([['type' => 'paragraph', 'content' => [editorText('Edited body')]]]);

    $this->actingAs($author)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/compose.update', $moment), [
            'title' => 'Edited title',
            'body' => $newBody,
        ])
        ->assertOk();

    $moment->refresh();

    expect($moment->title)->toBe('Edited title')
        ->and($moment->body)->toBe($newBody)
        ->and($moment->body_html)->toBe('<p>Edited body</p>');
});

it('rejects an update from anyone other than the moment\'s own author', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'Original title',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Original body')]]]),
        'body_html' => '<p>Original body</p>',
    ]);

    $this->actingAs($other)
        ->post(route('kopling-core::community/compose.update', $moment), [
            'title' => 'Hijacked title',
            'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Hijacked body')]]]),
        ])
        ->assertForbidden();

    $moment->refresh();

    expect($moment->title)->toBe('Original title');
});

it('denies the edit form to anyone other than the moment\'s own author', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'Original title',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Original body')]]]),
        'body_html' => '<p>Original body</p>',
    ]);

    $this->actingAs($other)
        ->get(route('kopling-core::community/compose.edit', $moment))
        ->assertForbidden();
});

it('prefills the edit form editor with the moment\'s existing document', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $body = editorDoc([['type' => 'paragraph', 'content' => [editorText('Existing body')]]]);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'Existing title',
        'body' => $body,
        'body_html' => '<p>Existing body</p>',
    ]);

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/compose.edit', $moment))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(e($body))
        ->and($html)->toContain('value="Existing title"');
});

it('shows an Edit action only to the moment\'s own author', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'A moment',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Some body')]]]),
        'body_html' => '<p>Some body</p>',
    ]);

    $authorHtml = $this->actingAs($author)
        ->get(route('kopling-core::community/community'))
        ->assertOk()
        ->getContent();

    expect($authorHtml)->toContain(route('kopling-core::community/compose.edit', $moment));

    $otherHtml = $this->actingAs($other)
        ->get(route('kopling-core::community/community'))
        ->assertOk()
        ->getContent();

    expect($otherHtml)->not->toContain(route('kopling-core::community/compose.edit', $moment));
});

it('denies compose.update to a guest', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'Original title',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Original body')]]]),
        'body_html' => '<p>Original body</p>',
    ]);

    $this->post(route('kopling-core::community/compose.update', $moment), [
        'title' => 'Hijacked title',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Hijacked body')]]]),
    ])->assertRedirect();

    $this->assertGuest();

    $moment->refresh();

    expect($moment->title)->toBe('Original title');
});

it('prefills the edit form with the moment\'s existing poll question and options', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'A poll moment',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Vote below')]]]),
        'body_html' => '<p>Vote below</p>',
    ]);

    $poll = Poll::create(['moment_id' => $moment->id, 'question' => 'Tabs or spaces?', 'results_visibility' => Poll::VISIBILITY_AFTER_VOTE]);
    $poll->options()->create(['label' => 'Tabs', 'position' => 0]);
    $poll->options()->create(['label' => 'Spaces', 'position' => 1]);

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/compose.edit', $moment))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="Tabs or spaces?"')
        ->and($html)->toContain('value="Tabs"')
        ->and($html)->toContain('value="Spaces"');
});

it('updates an existing poll (question and options) through compose.update, alongside the body', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $moment = Moment::create([
        'person_id' => $author->id,
        'title' => 'A poll moment',
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Vote below')]]]),
        'body_html' => '<p>Vote below</p>',
    ]);

    $poll = Poll::create(['moment_id' => $moment->id, 'question' => 'Tabs or spaces?', 'results_visibility' => Poll::VISIBILITY_AFTER_VOTE]);
    $poll->options()->create(['label' => 'Tabs', 'position' => 0]);
    $poll->options()->create(['label' => 'Spaces', 'position' => 1]);

    $this->actingAs($author)
        ->withHeader('HX-Request', 'true')
        ->post(route('kopling-core::community/compose.update', $moment), [
            'title' => 'A poll moment',
            'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Vote below')]]]),
            'poll_question' => 'Tabs or spaces, really?',
            'poll_options' => ['Tabs', 'Spaces forever'],
            'poll_option_emoji' => ['', ''],
            'poll_results_visibility' => 'after_vote',
        ])
        ->assertOk();

    $poll->refresh();

    expect($poll->question)->toBe('Tabs or spaces, really?')
        ->and($poll->options()->orderBy('position')->pluck('label')->all())->toBe(['Tabs', 'Spaces forever']);
});
