<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Tags\Tag;

it('always shows the upvote button before the downvote button, regardless of tag attach order', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob-order@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Order Me', 'body' => 'Body']);

    // Two tags, deliberately attached so a naive per-tag loop would interleave up/down out of
    // the desired order -- only sorting after collection (see Reaction::voteConfigFor) fixes it.
    $downOnly = Tag::forceCreate(['name' => 'Down Only', 'slug' => 'down-only', 'downvote_emoji' => '👎']);
    $upOnly = Tag::forceCreate(['name' => 'Up Only', 'slug' => 'up-only', 'upvote_emoji' => '🔥']);
    $moment->tags()->attach([$downOnly->id, $upOnly->id]);

    $html = $this->get('/')->assertOk()->getContent();

    expect(strpos($html, '🔥'))->toBeLessThan(strpos($html, '👎'));
});

it('renders vote buttons as circular, direction-colored, and distinct from the rail', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob-style@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Styled Moment', 'body' => 'Body']);

    Tag::forceCreate(['name' => 'Styling', 'slug' => 'styling', 'upvote_emoji' => '🔥', 'downvote_emoji' => '👎']);
    $moment->tags()->attach(Tag::where('slug', 'styling')->first()->id);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('btn-circle')
        ->and($html)->toContain('btn-outline btn-primary')
        ->and($html)->toContain('btn-outline btn-secondary')
        ->and($html)->toContain('indicator-item');
});
