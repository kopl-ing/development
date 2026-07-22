<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

function seedRepliesForDock(Moment $moment, Person $author, int $count): void
{
    collect(range(1, $count))->each(fn (int $i) => Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $author->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText("Reply {$i}")]]]),
        'body_html' => "<p>Reply {$i}</p>",
    ]));
}

it('counts the whole thread, not just the replies rendered on the current page', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    // One and a half pages' worth -- enough that page 1 alone doesn't hold the whole thread.
    $perPage = (new Reply())->getPerPage();
    seedRepliesForDock($moment, $author, (int) ($perPage * 1.5));

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    $total = (int) ($perPage * 1.5); // replies only -- the OP itself isn't a numbered post

    expect($html)->toContain("count: {$total}")
        // Page 1 has nothing preceding it.
        ->and($html)->toContain('pageBaseIndex: 0,')
        // The old, DOM-only count would have reported the page-1 subset as the whole thread.
        ->and($html)->not->toContain("count: {$perPage},");
});

it('offsets the base position on later pages instead of restarting from zero', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $perPage = (new Reply())->getPerPage();
    seedRepliesForDock($moment, $author, (int) ($perPage * 1.5));

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', ['moment' => $moment->id, 'page' => 2]))
        ->assertOk()
        ->getContent();

    $total = (int) ($perPage * 1.5);

    expect($html)->toContain("count: {$total}")
        ->and($html)->toContain("pageBaseIndex: {$perPage},");
});

it('keeps page one\'s base at zero even when a lone extra reply spills onto page two', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    // One more reply than a single page holds -- exactly the reported repro (11 replies, 10 per
    // page). `current` (pageBaseIndex + this page's own [data-reply] count, computed client-side
    // at scroll time) tops out at 0 + 10 = 10 while on page one, one short of the real total
    // (11) -- reaching page one's own pagination controls no longer reads as "the whole thread".
    $perPage = (new Reply())->getPerPage();
    seedRepliesForDock($moment, $author, $perPage + 1);

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('count: '.($perPage + 1))
        ->and($html)->toContain('pageBaseIndex: 0,');
});

it('embeds the wrapper data attributes and the resync listener a boosted page change relies on', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $perPage = (new Reply())->getPerPage();
    seedRepliesForDock($moment, $author, (int) ($perPage * 1.5));

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    $total = (int) ($perPage * 1.5);

    expect($html)->toContain("id=\"replies-wrapper-{$moment->id}\"")
        // The wrapper's own data attributes -- what syncFromRepliesPage() re-reads after a
        // boosted pagination swap, since the dock's own embedded count/pageBaseIndex otherwise
        // stay stuck on whichever page was loaded first.
        ->and($html)->toContain("data-total-replies=\"{$total}\"")
        ->and($html)->toContain('data-page-base-index="0"')
        ->and($html)->toContain("replies-wrapper-{$moment->id}")
        ->and($html)->toContain("window.addEventListener('htmx:after:settle'")
        ->and($html)->toContain('syncFromRepliesPage(e)');
});
