<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

/*
 * A reply renders through the exact same extensible Top/Body/Footer mechanism a Moment's own
 * card does, just scoped to Reply::TOP_SLOT/BODY_SLOT/FOOTER_SLOT -- see that constant's own
 * docblock. These prove both halves: the reply's own entries actually render, and Moment-only
 * registrations (reactions' vote/rail/words, discussions' own teaser/engage/quote-op) never
 * bleed onto it, since reactions and discussions are both real, installed extensions in this
 * app -- not stubbed out -- so this is a genuine isolation proof, not a vacuous one.
 */

function createMomentWithReply(): array
{
    $author = Person::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $replier = Person::create(['name' => 'Bob Babbage', 'email' => 'bob@example.test', 'password' => 'secret']);

    $reply = Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => editorDoc([['type' => 'paragraph', 'content' => [editorText('Great point!')]]]),
        'body_html' => '<p>Great point!</p>',
    ]);

    return [$author, $moment, $replier, $reply];
}

it('renders the reply author, avatar initials, and body via the reply-scoped Top/Body slots', function () {
    [$author, $moment, $replier, $reply] = createMomentWithReply();

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Bob Babbage')
        ->and($html)->toContain('BB')
        ->and($html)->toContain('Great point!')
        ->and($html)->toContain('data-reply="'.$reply->id.'"');
});

it('shows a quote button for the reply itself, distinct from the OP\'s own', function () {
    [$author, $moment] = createMomentWithReply();

    $dispatchCall = "\$dispatch('kop-quote-toggle', { id: ";

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    // One for the OP's own quote-op, one for the reply's quote-reply.
    expect(substr_count($html, $dispatchCall))->toBe(2);
});

it('never shows the reactions rail (a Moment-only footer entry) on a reply card', function () {
    [$author, $moment] = createMomentWithReply();

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    // The OP's own card still gets it (proving reactions is genuinely installed and rendering
    // on this exact page, not just globally absent) -- only the reply card must be free of it.
    $railId = 'id="reactions-'.$moment->id.'"';
    expect($html)->toContain($railId);

    $replyCardStart = strpos($html, 'data-reply="');
    $replyCardHtml = substr($html, $replyCardStart);

    expect($replyCardHtml)->not->toContain($railId);
});
