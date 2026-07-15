<?php

declare(strict_types=1);

/*
 * Exercises the real, already-installed reactions' `auth`-gated toggle route as the guest
 * hitting it -- proves `RedirectUnauthenticated` (registered once, in
 * `ServiceProvider::boot()`) handles both request shapes without ever falling through to
 * Laravel's default `unauthenticated()` handling, which would throw `RouteNotFoundException`
 * here since this app has no route literally named "login".
 */

it('redirects a plain guest request cleanly instead of throwing', function () {
    $this->post('/_reactions/not-a-real-id', ['emoji' => '👍'])
        ->assertRedirect();

    $this->assertGuest();
});

it('sends an HX-Redirect header for an htmx guest request', function () {
    $response = $this->withHeader('HX-Request', 'true')
        ->post('/_reactions/not-a-real-id', ['emoji' => '👍']);

    $response->assertStatus(401)
        ->assertHeader('HX-Redirect');

    $this->assertGuest();
});
