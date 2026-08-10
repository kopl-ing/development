<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Tags\Tag;
use Symfony\Component\DomCrawler\Crawler;

it('always shows the upvote button before the downvote button, regardless of tag attach order', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob-order@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Order Me', 'body' => 'Body']);

    // Two tags, deliberately attached so a naive per-tag loop would interleave up/down out of
    // the desired order -- only sorting after collection (see Reaction::voteConfigFor) fixes it.
    $downOnly = Tag::forceCreate(['name' => 'Down Only', 'slug' => 'down-only', 'downvote_emoji' => '👎']);
    $upOnly = Tag::forceCreate(['name' => 'Up Only', 'slug' => 'up-only', 'upvote_emoji' => '🔥']);
    $moment->tags()->attach([$downOnly->id, $upOnly->id]);

    $html = $this->get('/')->assertOk()->getContent();

    // Scoped to the vote widget's own emoji spans and their DOM order, not raw text position --
    // an emoji is free-form content that could otherwise appear a second time elsewhere on the
    // page before its own button. Not scoped to `button` specifically -- a guest (as here) gets
    // the calm, non-interactive `<span>` variant instead (see vote.blade.php's own `$canVote`).
    $emojiOrder = new Crawler($html)->filter('#votes-'.$moment->id.' span[aria-hidden]')
        ->each(fn (Crawler $span) => trim($span->text()));

    expect($emojiOrder)->toBe(['🔥', '👎']);
});

it('renders vote buttons as circular, direction-colored, and distinct from the rail', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob-style@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Styled Moment', 'body' => 'Body']);

    Tag::forceCreate(['name' => 'Styling', 'slug' => 'styling', 'upvote_emoji' => '🔥', 'downvote_emoji' => '👎']);
    $moment->tags()->attach(Tag::where('slug', 'styling')->first()->id);

    $html = $this->get('/')->assertOk()->getContent();
    $votes = new Crawler($html)->filter('#votes-'.$moment->id);

    expect($votes->filter('.btn-circle.btn-outline.btn-primary'))->toHaveCount(1)
        ->and($votes->filter('.btn-circle.btn-outline.btn-secondary'))->toHaveCount(1)
        ->and($votes->filter('.indicator-item'))->toHaveCount(2);
});
