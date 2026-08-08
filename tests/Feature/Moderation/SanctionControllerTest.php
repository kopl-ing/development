<?php

declare(strict_types=1);

use Kopling\Core\People\Sanction;

it('denies a guest issuing a sanction', function () {
    $person = moderationPerson('offender@example.test');

    $this->post(route('kopling-moderation::moderation/sanction.store', $person), ['reason' => 'spam'])
        ->assertForbidden();

    expect($person->fresh()->isAccessBlocked())->toBeFalse();
});

it('denies a person without moderate permission issuing a sanction', function () {
    $requester = moderationPerson('nobody@example.test');
    $person = moderationPerson('offender@example.test');

    $this->actingAs($requester)
        ->post(route('kopling-moderation::moderation/sanction.store', $person), ['reason' => 'spam'])
        ->assertForbidden();
});

it('issues a sanction covering all three axes through the real HTTP endpoint', function () {
    $moderator = personWithModeratePermission();
    $person = moderationPerson('offender@example.test');

    $this->actingAs($moderator)
        ->post(route('kopling-moderation::moderation/sanction.store', $person), [
            'communication_blocked' => '1',
            'hide_content' => '1',
            'access_blocked' => '1',
            'reason' => 'spam',
            'note' => 'Confirmed spam account',
        ])
        ->assertRedirect();

    $person->refresh();

    expect($person->communication_blocked_at)->not->toBeNull()
        ->and($person->visibility)->toBe('hidden')
        ->and($person->isAccessBlocked())->toBeTrue()
        ->and(Sanction::where('person_id', $person->id)->where('note', 'Confirmed spam account')->exists())->toBeTrue();
});

it('denies a person without moderate permission lifting a sanction', function () {
    $moderator = personWithModeratePermission();
    $requester = moderationPerson('nobody@example.test');
    $person = moderationPerson('offender@example.test');
    Sanction::issue($person, ['access_blocked' => true, 'reason' => 'spam'], $moderator);

    $this->actingAs($requester)
        ->post(route('kopling-moderation::moderation/sanction.lift', $person))
        ->assertForbidden();
});

it('lifts a sanction through the real HTTP endpoint', function () {
    $moderator = personWithModeratePermission();
    $person = moderationPerson('offender@example.test');
    Sanction::issue($person, ['access_blocked' => true, 'reason' => 'spam'], $moderator);

    $this->actingAs($moderator)
        ->post(route('kopling-moderation::moderation/sanction.lift', $person))
        ->assertRedirect();

    expect($person->fresh()->isAccessBlocked())->toBeFalse();
});

it('lists a currently-sanctioned person under the sanctioned queue tab', function () {
    $moderator = personWithModeratePermission();
    $person = moderationPerson('offender@example.test');
    Sanction::issue($person, ['communication_blocked' => true, 'reason' => 'spam'], $moderator);

    $this->actingAs($moderator)
        ->get(route('kopling-moderation::moderation/queue.index', ['status' => 'sanctioned']))
        ->assertOk()
        ->assertSee($person->name);
});
