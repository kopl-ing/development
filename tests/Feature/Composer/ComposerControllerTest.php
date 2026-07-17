<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

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
        ->post(route('kopling-core::community/compose.store'), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect(Moment::count())->toBe(0);
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
        ->post(route('kopling-core::community/compose.store'), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect(Moment::count())->toBe(0);
});
