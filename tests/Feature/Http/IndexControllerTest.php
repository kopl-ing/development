<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

it('renders pagination controls once there are enough moments to need a second page', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    // One more than a single page holds, whatever Moment::$perPage is currently set to -- not a
    // hardcoded count, so this doesn't silently start passing/failing every time that changes.
    $count = (new Moment())->getPerPage() + 1;

    collect(range(1, $count))->each(fn (int $i) => Moment::create([
        'person_id' => $author->id,
        'title' => "Moment {$i}",
        'body' => 'Body',
    ]));

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('aria-label="Next"')
        ->and($html)->toContain('page=2');
});

it('renders no pagination controls when everything fits on one page', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    Moment::create(['person_id' => $author->id, 'title' => 'Only One', 'body' => 'Body']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('aria-label="Next"');
});
