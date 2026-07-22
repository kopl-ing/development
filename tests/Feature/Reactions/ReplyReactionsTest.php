<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;
use Kopling\Reactions\Reaction;

/*
 * Proves the polymorphic side of reactions end to end: a Reply is reactable through the exact
 * same generic {type}/{id} toggle/word routes a Moment already uses, storing against the
 * 'reply' morph-map alias rather than a hardcoded `moment_id` -- see decisions.md's "reactions
 * become polymorphic" entry for the schema/routing shape this exercises.
 */

function createMomentWithReplyForReactions(): array
{
    $author = Person::create(['name' => 'Ada', 'email' => 'ada-reply-reactions@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $replier = Person::create(['name' => 'Bob', 'email' => 'bob-reply-reactions@example.test', 'password' => 'secret']);
    $reply = Reply::create([
        'moment_id' => $moment->id,
        'person_id' => $replier->id,
        'body' => 'Nice one',
        'body_html' => '<p>Nice one</p>',
    ]);

    return [$author, $moment, $reply];
}

it('lets a signed-in person react to a reply via the generic toggle route, stored under the reply alias', function () {
    [, , $reply] = createMomentWithReplyForReactions();
    $reactor = Person::create(['name' => 'Cleo', 'email' => 'cleo-reply-reactions@example.test', 'password' => 'secret']);

    $this->actingAs($reactor)
        ->post("/_xhr/kopling-reactions/reply/{$reply->id}", ['emoji' => '🎉'])
        ->assertOk();

    expect(Reaction::where('reactable_type', 'reply')->where('reactable_id', $reply->id)->where('person_id', $reactor->id)->where('emoji', '🎉')->exists())->toBeTrue();
});

it('renders the rail on the reply\'s own card, showing an applied reaction', function () {
    [$author, $moment, $reply] = createMomentWithReplyForReactions();
    $reactor = Person::create(['name' => 'Cleo', 'email' => 'cleo-reply-reactions-2@example.test', 'password' => 'secret']);

    $this->actingAs($reactor)->post("/_xhr/kopling-reactions/reply/{$reply->id}", ['emoji' => '🎉'])->assertOk();

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('🎉')
        ->and($html)->toContain('id="reactions-'.$reply->id.'"');
});

it('adds a worded reaction to a reply through the generic word route', function () {
    [, , $reply] = createMomentWithReplyForReactions();
    $reactor = Person::create(['name' => 'Cleo', 'email' => 'cleo-reply-reactions-3@example.test', 'password' => 'secret']);

    $response = $this->actingAs($reactor)
        ->post("/_xhr/kopling-reactions/reply/{$reply->id}/word", ['emoji' => '👍', 'word' => 'so true'])
        ->assertOk();

    expect($response->getContent())->toContain('so true');
    expect(Reaction::where('reactable_type', 'reply')->where('reactable_id', $reply->id)->where('word', 'so true')->exists())->toBeTrue();
});

it('404s the generic route for a type that is not a registered morph-map alias', function () {
    [, , $reply] = createMomentWithReplyForReactions();
    $reactor = Person::create(['name' => 'Cleo', 'email' => 'cleo-reply-reactions-4@example.test', 'password' => 'secret']);

    $this->actingAs($reactor)
        ->post("/_xhr/kopling-reactions/bogus-type/{$reply->id}", ['emoji' => '🎉'])
        ->assertNotFound();
});

it('never registers vote on a reply -- voting stays moment-only, tag-configured', function () {
    [$author, $moment, $reply] = createMomentWithReplyForReactions();

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    // The reply card renders (proving the page itself works), but carries no votes-{id} rail --
    // that component is only ever registered into the Moment footer, never the Reply one.
    expect($html)->toContain('data-reply="'.$reply->id.'"')
        ->and($html)->not->toContain('id="votes-'.$reply->id.'"');
});
