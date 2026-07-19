<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;
use Kopling\Tags\Tag;

it('returns null for a tag with no moments at all', function () {
    $tag = Tag::create(['name' => 'Empty', 'slug' => 'empty-latest-activity']);

    expect($tag->latestActivity())->toBeNull();
});

it('returns the newest moment\'s created_at when there are no replies', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-latest-activity']);

    $older = Moment::create(['person_id' => $author->id, 'title' => 'Older', 'body' => 'Old']);
    $older->tags()->attach($tag);
    $older->forceFill(['created_at' => now()->subDays(5)])->save();

    $newer = Moment::create(['person_id' => $author->id, 'title' => 'Newer', 'body' => 'New']);
    $newer->tags()->attach($tag);
    $newer->forceFill(['created_at' => now()->subDay()])->save();

    expect($tag->latestActivity()->isSameDay($newer->created_at))->toBeTrue();
});

it('returns a reply\'s timestamp when it is more recent than the moment itself', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-latest-activity-reply']);

    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Old thread', 'body' => 'Old']);
    $moment->tags()->attach($tag);
    $moment->forceFill(['created_at' => now()->subDays(10)])->save();

    $reply = Reply::create(['moment_id' => $moment->id, 'person_id' => $replier->id, 'body' => 'Still going', 'body_html' => '<p>Still going</p>']);
    $reply->forceFill(['created_at' => now()->subHour()])->save();

    expect($tag->latestActivity()->isSameDay($reply->created_at))->toBeTrue()
        ->and($tag->latestActivity()->diffInDays(now()))->toBeLessThan(1);
});
