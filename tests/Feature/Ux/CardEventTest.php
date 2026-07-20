<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Card\Event\RenderingCard;
use Kopling\Core\Ux\Context;

/*
 * `Card` dispatches `RenderingCard` through the real event dispatcher (see `Card`'s own
 * constructor) -- registering a plain listener via `Event::listen()` here exercises the actual
 * mechanism end to end, the same way `ListensToEvents` extensions will consume it, without
 * needing a disposable fixture extension for a core-mechanism test like this one.
 */

it('renders with no extra classes when nothing listens', function () {
    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $person->id, 'title' => 'Hello', 'body' => 'World']);

    $html = (string) $this->blade('<x-k::card.card :context="$context" />', [
        'context' => new Context(subject: $moment),
    ]);

    expect($html)->toContain('class="card bg-base-100 outline -outline-offset-1 outline-base-content/10"');
});

it('lets a RenderingCard listener append a class to the card wrapper', function () {
    Event::listen(RenderingCard::class, function (RenderingCard $event) {
        $event->addClass('border-warning');
    });

    $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $person->id, 'title' => 'Hello', 'body' => 'World']);

    $html = (string) $this->blade('<x-k::card.card :context="$context" />', [
        'context' => new Context(subject: $moment),
    ]);

    expect($html)->toContain('border-warning');
});
