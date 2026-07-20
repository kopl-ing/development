<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Reactions\Reaction;

/*
 * A worded reaction's own chip *is* the remove control for its own author -- a real <button>,
 * clicking anywhere on it posts to the same toggle route that already deletes a plain reaction
 * by (reactable, person, emoji), no separate endpoint and no small bolted-on affordance. The
 * toggle route's response now also carries the words strip back out-of-band, so the chip that
 * triggered its own removal actually disappears, not just the rail.
 */

it('lets the reacting person remove their own worded reaction by clicking their chip, reusing the toggle route', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada-remove-worded@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $reactor = Person::create(['name' => 'Bob', 'email' => 'bob-remove-worded@example.test', 'password' => 'secret']);

    $this->actingAs($reactor)
        ->post("/_reactions/moment/{$moment->id}/word", ['emoji' => '👍', 'word' => 'so true'])
        ->assertOk();

    expect(Reaction::where('reactable_type', 'moment')->where('reactable_id', $moment->id)->exists())->toBeTrue();

    // The chip itself is the button -- clicking it posts to the *toggle* route (not a dedicated
    // endpoint) -- this is that exact request.
    $response = $this->actingAs($reactor)
        ->post("/_reactions/moment/{$moment->id}", ['emoji' => '👍'])
        ->assertOk();

    expect(Reaction::where('reactable_type', 'moment')->where('reactable_id', $moment->id)->exists())->toBeFalse();
    expect($response->getContent())->not->toContain('so true');
});

it('renders someone else\'s worded reaction chip as a plain, non-clickable span', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada-remove-worded2@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $reactor = Person::create(['name' => 'Bob', 'email' => 'bob-remove-worded2@example.test', 'password' => 'secret']);
    $viewer = Person::create(['name' => 'Cleo', 'email' => 'cleo-remove-worded2@example.test', 'password' => 'secret']);

    $this->actingAs($reactor)
        ->post("/_reactions/moment/{$moment->id}/word", ['emoji' => '👍', 'word' => 'so true'])
        ->assertOk();

    $html = $this->actingAs($viewer)->get('/')->assertOk()->getContent();

    expect($html)->toContain('so true')
        ->and($html)->not->toContain('kop-rchip--mine');
});

it('renders the viewer\'s own worded reaction chip as the clickable button variant', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada-remove-worded3@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $reactor = Person::create(['name' => 'Bob', 'email' => 'bob-remove-worded3@example.test', 'password' => 'secret']);

    $this->actingAs($reactor)
        ->post("/_reactions/moment/{$moment->id}/word", ['emoji' => '👍', 'word' => 'so true'])
        ->assertOk();

    $html = $this->actingAs($reactor)->get('/')->assertOk()->getContent();

    expect($html)->toContain('kop-rchip--mine')
        ->and($html)->toContain('so true');
});
