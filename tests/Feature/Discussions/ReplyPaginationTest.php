<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

function seedReplies(Moment $moment, Person $author, int $count): void
{
    collect(range(1, $count))->each(fn (int $i) => Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $author->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText("Reply {$i}")]]]),
        'body_html' => "<p>Reply {$i}</p>",
    ]));
}

it('renders pagination controls once a thread has enough replies to need a second page', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    // One more than a single page holds, whatever Reply::$perPage is currently set to -- not a
    // hardcoded count, so this doesn't silently start passing/failing every time that changes.
    $count = (new Reply())->getPerPage() + 1;

    seedReplies($moment, $author, $count);

    $html = $this->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('aria-label="Next"')
        ->and($html)->toContain('page=2')
        // The heading counts the whole thread, not just what fits on this one page.
        ->and($html)->toContain("{$count} replies");
});

it('renders no pagination controls when the whole thread fits on one page', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    seedReplies($moment, $author, 2);

    $html = $this->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('aria-label="Next"')
        ->and($html)->toContain('2 replies');
});
