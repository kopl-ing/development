<?php

declare(strict_types=1);

use Kopling\Core\People\Person;
use Kopling\Core\People\Sanction;

function personForSanctionEnforcement(string $email = 'person@example.test'): Person
{
    return Person::create(['name' => 'Person', 'email' => $email, 'password' => 'secret']);
}

it('lets an unsanctioned, already-authenticated person keep browsing normally', function () {
    $person = personForSanctionEnforcement();

    $this->actingAs($person)->get(route('kopling-core::community/community'))->assertOk();
});

it('logs a mid-session access-blocked person out on their very next request, not just at login', function () {
    $moderator = personForSanctionEnforcement('mod@example.test');
    $person = personForSanctionEnforcement();

    $this->actingAs($person);
    $this->assertAuthenticatedAs($person);

    Sanction::issue($person, ['access_blocked' => true, 'reason' => 'spam'], $moderator);

    $this->get(route('kopling-core::community/community'))
        ->assertRedirect(route('kopling-core::community/access-blocked'));

    $this->assertGuest();
});

it('does not touch a person whose access is blocked by a temporary suspend that has already expired', function () {
    $moderator = personForSanctionEnforcement('mod@example.test');
    $person = personForSanctionEnforcement();

    Sanction::issue($person, [
        'access_blocked' => true,
        'access_blocked_until' => now()->subMinute(),
        'reason' => 'spam',
    ], $moderator);

    $this->actingAs($person)->get(route('kopling-core::community/community'))->assertOk();

    $this->assertAuthenticatedAs($person);
});

it('rejects a login attempt for an access-blocked person before a session is ever established', function () {
    $moderator = personForSanctionEnforcement('mod@example.test');
    $person = personForSanctionEnforcement();
    Sanction::issue($person, ['access_blocked' => true, 'reason' => 'spam'], $moderator);

    $this->post(route('kopling-core::community/login.attempt'), [
        'email' => $person->email,
        'password' => 'secret',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('shows the access-blocked page with the reason and a permanent-restriction notice', function () {
    $moderator = personForSanctionEnforcement('mod@example.test');
    $person = personForSanctionEnforcement();
    $this->actingAs($person);

    Sanction::issue($person, ['access_blocked' => true, 'reason' => 'illegal', 'note' => 'Serious violation'], $moderator);

    $response = $this->followingRedirects()->get(route('kopling-core::community/community'));

    $response->assertOk()
        ->assertSee('Illegal')
        ->assertSee('Serious violation');
});
