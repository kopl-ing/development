<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Tags\Tag;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The feed's actual render order, by each moment card's own `id="moment-{id}"` (see
 * `community/moment.blade.php`) -- not each moment's title text, which is free-form and could in
 * principle appear a second time elsewhere on the page (another card's teaser, a meta tag) before
 * the card it actually belongs to, giving a false position.
 *
 * @return array<int, string>
 */
function feedMomentOrder(string $html): array
{
    return new Crawler($html)->filter('[id^="moment-"]')->each(fn (Crawler $card) => $card->attr('id'));
}

it('orders the feed by upvote count when sort=top is requested', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob-sort@example.test', 'password' => 'secret']);
    $voter = Person::create(['name' => 'Ada', 'email' => 'ada-sort@example.test', 'password' => 'secret']);

    Tag::forceCreate(['name' => 'Requests', 'slug' => 'requests-sort', 'upvote_emoji' => '👍']);

    $older = Moment::create(['person_id' => $author->id, 'title' => 'Older Low Votes', 'body' => 'Body']);
    $older->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $newer = Moment::create(['person_id' => $author->id, 'title' => 'Newer High Votes', 'body' => 'Body']);
    $newer->forceFill(['created_at' => now()])->save();

    $newer->reactions()->create(['person_id' => $voter->id, 'emoji' => '👍']);

    // Chronologically $newer already sorts first -- the real assertion here is that a vote on
    // the OLDER moment still wins under sort=top, proving the query is ordered by votes_count
    // and not merely falling back to its own chronological default.
    $older->reactions()->create(['person_id' => $voter->id, 'emoji' => '👍']);
    $secondVoter = Person::create(['name' => 'Cleo', 'email' => 'cleo-sort@example.test', 'password' => 'secret']);
    $older->reactions()->create(['person_id' => $secondVoter->id, 'emoji' => '👍']);

    $html = $this->get('/?sort=top')->assertOk()->getContent();
    $order = feedMomentOrder($html);

    expect(array_search('moment-'.$older->id, $order))->toBeLessThan(array_search('moment-'.$newer->id, $order));
});

it('stays chronological by default even when a tag configures voting', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob-sort2@example.test', 'password' => 'secret']);
    $voter = Person::create(['name' => 'Ada', 'email' => 'ada-sort2@example.test', 'password' => 'secret']);

    Tag::forceCreate(['name' => 'Requests', 'slug' => 'requests-sort2', 'upvote_emoji' => '👍']);

    $older = Moment::create(['person_id' => $author->id, 'title' => 'Older No Sort', 'body' => 'Body']);
    $older->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $newer = Moment::create(['person_id' => $author->id, 'title' => 'Newer No Sort', 'body' => 'Body']);
    $newer->forceFill(['created_at' => now()])->save();

    // The older moment has the votes -- if the query were still (accidentally) ordering by
    // votes here, it would sort first. It must not: sort=top wasn't requested.
    $older->reactions()->create(['person_id' => $voter->id, 'emoji' => '👍']);

    $html = $this->get('/')->assertOk()->getContent();
    $order = feedMomentOrder($html);

    expect(array_search('moment-'.$newer->id, $order))->toBeLessThan(array_search('moment-'.$older->id, $order));
});
