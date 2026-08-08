<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kopling\Core\Moderation\Event\PersonSanctioned;
use Kopling\Core\Moderation\Event\PersonSanctionLifted;
use Kopling\Core\People\Person;
use Kopling\Core\People\Sanction;

function sanctionPerson(string $email): Person
{
    return Person::create(['name' => 'Sanctioned', 'email' => $email, 'password' => 'secret']);
}

it('issues a sanction, writing a row and updating the person\'s own live columns in the same call', function () {
    $moderator = sanctionPerson('mod@example.test');
    $person = sanctionPerson('offender@example.test');

    $sanction = Sanction::issue($person, [
        'communication_blocked' => true,
        'visibility' => 'hidden',
        'access_blocked' => true,
        'access_blocked_until' => null,
        'reason' => 'spam',
        'note' => 'Repeated spam posts',
    ], $moderator);

    expect($sanction->person_id)->toBe($person->id)
        ->and($sanction->issued_by)->toBe($moderator->id)
        ->and($sanction->lifted_at)->toBeNull();

    $person->refresh();

    expect($person->communication_blocked_at)->not->toBeNull()
        ->and($person->visibility)->toBe('hidden')
        ->and($person->isAccessBlocked())->toBeTrue();
});

it('fires PersonSanctioned', function () {
    $seen = null;
    Event::listen(PersonSanctioned::class, function (PersonSanctioned $event) use (&$seen) {
        $seen = $event;
    });

    $moderator = sanctionPerson('mod@example.test');
    $person = sanctionPerson('offender@example.test');

    $sanction = Sanction::issue($person, ['reason' => 'spam'], $moderator);

    expect($seen)->not->toBeNull()
        ->and($seen->person->id)->toBe($person->id)
        ->and($seen->sanction->id)->toBe($sanction->id);
});

it('a second sanction supersedes the first, keeping at most one active sanction per person', function () {
    $moderator = sanctionPerson('mod@example.test');
    $person = sanctionPerson('offender@example.test');

    $first = Sanction::issue($person, ['communication_blocked' => true, 'reason' => 'spam'], $moderator);
    $second = Sanction::issue($person, ['access_blocked' => true, 'reason' => 'illegal'], $moderator);

    expect($first->fresh()->lifted_at)->not->toBeNull()
        ->and($first->fresh()->lifted_by)->toBeNull()
        ->and($second->fresh()->lifted_at)->toBeNull();

    // The second issue() fully re-specifies all three axes -- communication_blocked wasn't
    // passed this time, so it resets to false rather than staying true from the first sanction.
    $person->refresh();
    expect($person->communication_blocked_at)->toBeNull()
        ->and($person->isAccessBlocked())->toBeTrue();
});

it('lifts the currently active sanction, clearing the person\'s live columns', function () {
    $moderator = sanctionPerson('mod@example.test');
    $person = sanctionPerson('offender@example.test');
    Sanction::issue($person, ['access_blocked' => true, 'reason' => 'spam'], $moderator);

    $lifted = Sanction::lift($person, $moderator);

    expect($lifted)->not->toBeNull()
        ->and($lifted->lifted_by)->toBe($moderator->id)
        ->and($lifted->lifted_at)->not->toBeNull();

    $person->refresh();
    expect($person->isAccessBlocked())->toBeFalse()
        ->and($person->visibility)->toBe('normal');
});

it('fires PersonSanctionLifted', function () {
    $seen = null;
    Event::listen(PersonSanctionLifted::class, function (PersonSanctionLifted $event) use (&$seen) {
        $seen = $event;
    });

    $moderator = sanctionPerson('mod@example.test');
    $person = sanctionPerson('offender@example.test');
    Sanction::issue($person, ['access_blocked' => true, 'reason' => 'spam'], $moderator);

    Sanction::lift($person, $moderator);

    expect($seen)->not->toBeNull()
        ->and($seen->person->id)->toBe($person->id);
});

it('returns null when lifting a person with no active sanction', function () {
    $moderator = sanctionPerson('mod@example.test');
    $person = sanctionPerson('clean@example.test');

    expect(Sanction::lift($person, $moderator))->toBeNull();
});

it('isAccessBlocked() treats a temporary suspend as expired once access_blocked_until has passed', function () {
    $moderator = sanctionPerson('mod@example.test');
    $person = sanctionPerson('offender@example.test');

    Sanction::issue($person, [
        'access_blocked' => true,
        'access_blocked_until' => now()->subMinute(),
        'reason' => 'spam',
    ], $moderator);

    expect($person->fresh()->isAccessBlocked())->toBeFalse();
});
