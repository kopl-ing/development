<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Pin\Pin;
use Symfony\Component\DomCrawler\Crawler;

it('renders a pinned moment once, in the pinned section, decorated -- not duplicated in the regular feed', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $pinnedMoment = Moment::create(['person_id' => $author->id, 'title' => 'Pinned One', 'body' => 'Body A']);
    $plainMoment = Moment::create(['person_id' => $author->id, 'title' => 'Plain One', 'body' => 'Body B']);

    Pin::create(['moment_id' => $pinnedMoment->id, 'reason' => 'announcement']);

    $html = $this->get('/')->assertOk()->getContent();

    // Scoped to card titles specifically (Card\Title's own `h2.card-title`), not raw text
    // position -- a title is free-form and the pinned section renders its card via a plain
    // `<x-k::card.card>` with no id of its own to anchor a check to instead (unlike the regular
    // feed's own per-item `id="moment-{id}"`, see community/moment.blade.php).
    $titles = array_count_values(
        new Crawler($html)->filter('h2.card-title')->each(fn (Crawler $h2) => trim($h2->text()))
    );

    expect($titles['Pinned One'] ?? 0)->toBe(1)
        ->and($titles['Plain One'] ?? 0)->toBe(1)
        ->and($html)->toContain('outline-info');
});

it('shows a Groups-targeted pin as a normal, undecorated moment to a viewer outside the targeted groups', function () {
    $author = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Targeted Pin', 'body' => 'Body']);

    $group = Group::create(['name' => 'VIPs']);
    $pin = Pin::create(['moment_id' => $moment->id, 'reason' => 'help']);
    $pin->groups()->attach($group);

    $outsider = Person::create(['name' => 'Eve', 'email' => 'eve@example.test', 'password' => 'secret']);

    $html = $this->actingAs($outsider)->get('/')->assertOk()->getContent();

    // The Moment itself still shows -- Groups targeting scopes who sees it as *pinned*, not
    // whether the Moment exists in the feed at all -- but exactly once, and undecorated.
    $titles = array_count_values(
        new Crawler($html)->filter('h2.card-title')->each(fn (Crawler $h2) => trim($h2->text()))
    );

    expect($titles['Targeted Pin'] ?? 0)->toBe(1)
        ->and($html)->not->toContain('outline-success');
});
