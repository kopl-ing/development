<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;

function momentWithAuthor(): Moment
{
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    return Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
}

it('returns distinct repliers only, most-recent-reply-first', function () {
    $moment = momentWithAuthor();

    $bob = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $cleo = Person::create(['name' => 'Cleo', 'email' => 'cleo@example.test', 'password' => 'secret']);

    // Explicit, distinct timestamps -- created_at has only second-level precision, so three
    // inserts made back-to-back in the same test could otherwise tie and make ordering flaky.
    $first = Reply::create(['moment_id' => $moment->id, 'person_id' => $bob->id, 'body' => 'First', 'body_html' => '<p>First</p>']);
    $first->forceFill(['created_at' => now()->subMinutes(3)])->save();

    $second = Reply::create(['moment_id' => $moment->id, 'person_id' => $cleo->id, 'body' => 'Second', 'body_html' => '<p>Second</p>']);
    $second->forceFill(['created_at' => now()->subMinutes(2)])->save();

    // A second, more recent reply from Bob shouldn't produce a second entry for him -- but it
    // should be what determines his position (most-recent-reply-first).
    $third = Reply::create(['moment_id' => $moment->id, 'person_id' => $bob->id, 'body' => 'Third', 'body_html' => '<p>Third</p>']);
    $third->forceFill(['created_at' => now()->subMinute()])->save();

    $contributors = Reply::recentContributors($moment);

    expect($contributors)->toHaveCount(2)
        ->and($contributors->pluck('name')->all())->toBe(['Bob', 'Cleo']);
});

it('caps the result at the given limit', function () {
    $moment = momentWithAuthor();

    foreach (range(1, 8) as $i) {
        $person = Person::create(['name' => "Person {$i}", 'email' => "person{$i}@example.test", 'password' => 'secret']);
        Reply::create(['moment_id' => $moment->id, 'person_id' => $person->id, 'body' => "Reply {$i}", 'body_html' => "<p>Reply {$i}</p>"]);
    }

    expect(Reply::recentContributors($moment, 3))->toHaveCount(3);
});

it('returns an empty collection for a moment with no replies', function () {
    $moment = momentWithAuthor();

    expect(Reply::recentContributors($moment))->toBeEmpty();
});
