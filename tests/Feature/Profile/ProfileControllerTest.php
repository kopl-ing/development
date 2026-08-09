<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

it('shows a Replies tab next to Moments, listing only that person\'s own replies', function () {
    $owner = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $other->id, 'title' => 'Hello', 'body' => 'World']);

    Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $owner->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Great point!')]]]),
        'body_html' => '<p>Great point!</p>',
    ]);
    Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $other->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Not this one')]]]),
        'body_html' => '<p>Not this one</p>',
    ]);

    $html = $this->get(route('kopling-core::community/profile.show', $owner))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('1 reply')
        ->and($html)->toContain('Great point!')
        ->and($html)->not->toContain('Not this one');
});

it('paginates Moments and Replies independently, on separate query params', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $momentCount = (new Moment())->getPerPage() + 1;
    collect(range(1, $momentCount))->each(fn (int $i) => Moment::create([
        'person_id' => $person->id,
        'title' => "Moment {$i}",
        'body' => 'Body',
    ]));

    $moment = Moment::first();
    $replyCount = (new Reply())->getPerPage() + 1;
    collect(range(1, $replyCount))->each(fn (int $i) => Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $person->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText("Reply {$i}")]]]),
        'body_html' => "<p>Reply {$i}</p>",
    ]));

    $html = $this->get(route('kopling-core::community/profile.show', $person))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('page=2')
        ->and($html)->toContain('replies_page=2');
});
