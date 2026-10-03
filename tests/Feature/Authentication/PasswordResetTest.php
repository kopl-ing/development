<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Kopling\AuthEmailPassword\Extension;
use Kopling\AuthEmailPassword\ResetPasswordNotification;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\Settings;

it('mails a reset link, answers the same for unknown addresses, and sets the new password', function () {
    Notification::fake();
    $person = Person::create(['name' => 'Sam', 'email' => 'sam@example.test', 'password' => 'old-password-123']);

    $this->get('/login')->assertSee('href="'.url('/forgot-password').'"', false);
    $this->get('/forgot-password')->assertOk();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.test'])
        ->assertRedirect('/forgot-password')->assertSessionHas('status');
    Notification::assertNothingSent();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'sam@example.test'])
        ->assertRedirect('/forgot-password')->assertSessionHas('status');

    $token = null;
    Notification::assertSentTo($person, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($person, &$token) {
        $token = $notification->token;

        return str_contains($notification->url($person), '/forgot-password/'.$token)
            && str_contains($notification->toMail($person)->actionUrl, $token);
    });

    $this->get('/forgot-password/'.$token.'?email=sam@example.test')->assertOk()->assertSee('value="sam@example.test"', false);

    $this->post('/forgot-password/wrong-token', ['email' => 'sam@example.test', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
        ->assertSessionHasErrors('email');

    $this->post('/forgot-password/'.$token, ['email' => 'sam@example.test', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
        ->assertRedirect(url('/login'))->assertSessionHas('status');

    expect(Hash::check('new-password-123', $person->fresh()->password))->toBeTrue()
        ->and($person->fresh()->email_verified_at)->not->toBeNull();
    $this->get('/login')->assertSee('Your password has been changed.');
    $this->post('/login', ['email' => 'sam@example.test', 'password' => 'new-password-123'])->assertRedirect();
    $this->assertAuthenticatedAs($person->fresh());
});

it('throttles reset requests and reset attempts', function () {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post('/forgot-password', ['email' => "nobody$attempt@example.test"])->assertRedirect();
    }
    $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertTooManyRequests();

    foreach (range(1, 5) as $attempt) {
        $this->post('/forgot-password/guess-'.$attempt, ['email' => 'x@example.test', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']);
    }
    $this->post('/forgot-password/guess', ['email' => 'x@example.test', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertTooManyRequests();
});

it('serves the reset pages on a custom path', function () {
    Settings::set(Extension::PASSWORD_RESET_PATH, 'reset');
    reloadRoutes($this->app);

    $this->get('/reset')->assertOk();
    $this->get('/forgot-password')->assertNotFound();
});
