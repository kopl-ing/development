<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Reactions\Reaction;
use Kopling\Tags\Tag;

function authorForVoteTest(): Person
{
    return Person::create(['name' => 'Bob', 'email' => 'bob-vote@example.test', 'password' => 'secret']);
}

function voterForVoteTest(): Person
{
    return Person::create(['name' => 'Ada', 'email' => 'ada-vote@example.test', 'password' => 'secret']);
}

it('denies a guest entirely', function () {
    $author = authorForVoteTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $this->post("/_reactions/{$moment->id}/vote", ['emoji' => '👍'])->assertRedirect();
    $this->assertGuest();
});

it('accepts an emoji configured on the moment tag as an upvote', function () {
    $author = authorForVoteTest();
    $voter = voterForVoteTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $tag = Tag::create(['name' => 'Requests', 'slug' => 'requests-vote', 'upvote_emoji' => '👍', 'downvote_emoji' => '👎']);
    $moment->tags()->attach($tag->id);

    $this->actingAs($voter)
        ->post("/_reactions/{$moment->id}/vote", ['emoji' => '👍'])
        ->assertOk();

    expect(Reaction::where('moment_id', $moment->id)->where('person_id', $voter->id)->where('emoji', '👍')->exists())->toBeTrue();
});

it('rejects an emoji not configured for that moment', function () {
    $author = authorForVoteTest();
    $voter = voterForVoteTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $tag = Tag::create(['name' => 'Requests', 'slug' => 'requests-vote-2', 'upvote_emoji' => '👍']);
    $moment->tags()->attach($tag->id);

    $this->actingAs($voter)
        ->post("/_reactions/{$moment->id}/vote", ['emoji' => '😂'])
        ->assertStatus(422);
});

it('rejects any vote when the moment carries no tag with voting configured', function () {
    $author = authorForVoteTest();
    $voter = voterForVoteTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $this->actingAs($voter)
        ->post("/_reactions/{$moment->id}/vote", ['emoji' => '👍'])
        ->assertStatus(422);
});

it('toggles the vote off on a second identical submission', function () {
    $author = authorForVoteTest();
    $voter = voterForVoteTest();
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $tag = Tag::create(['name' => 'Requests', 'slug' => 'requests-vote-3', 'upvote_emoji' => '👍']);
    $moment->tags()->attach($tag->id);

    $this->actingAs($voter)->post("/_reactions/{$moment->id}/vote", ['emoji' => '👍']);
    $this->actingAs($voter)->post("/_reactions/{$moment->id}/vote", ['emoji' => '👍']);

    expect(Reaction::where('moment_id', $moment->id)->where('person_id', $voter->id)->exists())->toBeFalse();
});
