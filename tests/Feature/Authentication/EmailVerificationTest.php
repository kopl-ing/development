<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Kopling\AuthEmailPassword\VerifyEmailNotification;
use Kopling\Core\People\Person;

it('holds a new account back until its email is confirmed, then lets it sign in', function () {
    Notification::fake();

    signUp($this)->assertRedirect(url('/verify-email'));
    $this->assertGuest();
    $person = Person::where('email', 'sam@example.test')->firstOrFail();
    expect($person->email_verified_at)->toBeNull();

    $url = null;
    Notification::assertSentTo($person, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($person, &$url) {
        $url = $notification->url($person);

        return true;
    });
    $this->get('/verify-email')->assertOk()->assertSee('Check your email')->assertSee('value="sam@example.test"', false);

    $this->post('/login', ['email' => 'sam@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email')->assertSessionMissing('verification_email');
    $this->post('/login', ['email' => 'sam@example.test', 'password' => 'correct-horse-battery'])
        ->assertSessionHasErrors('email')->assertSessionHas('verification_email', 'sam@example.test');
    $this->assertGuest();

    $this->get($url)->assertRedirect(url('/login'))->assertSessionHas('status');
    $this->assertGuest();
    expect($person->fresh()->email_verified_at)->not->toBeNull();

    $this->post('/login', ['email' => 'sam@example.test', 'password' => 'correct-horse-battery'])->assertRedirect();
    $this->assertAuthenticatedAs($person->fresh());
});

it('refuses a tampered or expired link', function () {
    $person = Person::create(['name' => 'Sam', 'email' => 'sam@example.test', 'password' => 'secret-password']);
    $valid = (new VerifyEmailNotification())->url($person);

    $this->get(str_replace(sha1('sam@example.test'), sha1('other@example.test'), $valid))->assertForbidden();
    $this->get(URL::temporarySignedRoute('kopling-core::community/verification.verify', now()->subMinute(), ['id' => $person->id, 'hash' => sha1('sam@example.test')]))->assertForbidden();
    expect($person->fresh()->email_verified_at)->toBeNull();
});

it('resends only to unverified accounts, answers the same for any address, and throttles', function () {
    Notification::fake();
    $waiting = Person::create(['name' => 'Sam', 'email' => 'sam@example.test', 'password' => 'secret-password']);
    $verified = Person::create(['name' => 'Kim', 'email' => 'kim@example.test', 'password' => 'secret-password']);
    $verified->forceFill(['email_verified_at' => now()])->save();

    foreach (['sam@example.test', 'kim@example.test', 'nobody@example.test'] as $email) {
        $this->post('/verify-email', ['email' => $email])->assertRedirect(url('/verify-email'))->assertSessionHas('status');
    }
    Notification::assertSentTo($waiting, VerifyEmailNotification::class);
    Notification::assertNotSentTo($verified, VerifyEmailNotification::class);

    $this->post('/verify-email', ['email' => 'sam@example.test'])->assertTooManyRequests();
});

it('signs up straight away when an admin turned verification off', function () {
    Notification::fake();
    \Kopling\Core\Settings\Settings::set(\Kopling\AuthEmailPassword\Extension::VERIFICATION_REQUIRED, '0');
    signUp($this)->assertRedirect();
    $this->assertAuthenticated();
    Notification::assertNothingSent();
});

it('serves log-in and verification on custom paths, and mails links on the new path', function () {
    Notification::fake();
    \Kopling\Core\Settings\Settings::set(\Kopling\Core\Authentication\AuthSettings::LOGIN_PATH, 'sign-in');
    \Kopling\Core\Settings\Settings::set(\Kopling\AuthEmailPassword\Extension::VERIFICATION_PATH, 'confirm');
    reloadRoutes($this->app);

    $this->get('/sign-in')->assertOk();
    $this->get('/login')->assertNotFound();
    $this->get('/settings')->assertRedirect(url('/sign-in'));

    signUp($this)->assertRedirect(url('/confirm'));
    $person = Person::where('email', 'sam@example.test')->firstOrFail();
    Notification::assertSentTo($person, VerifyEmailNotification::class, fn ($notification) => str_contains($notification->url($person), '/confirm/'.$person->id.'/'));
    $this->get('/verify-email')->assertNotFound();
});

it('renders the auth pages, errors and mails in Dutch on a Dutch site', function () {
    app()->setLocale('nl');
    $person = Person::create(['name' => 'Sam', 'email' => 'sam@example.test', 'password' => 'secret-password']);

    $this->get('/login')->assertOk()->assertSee('Inloggen')->assertSee('Wachtwoord vergeten?')->assertSee('Ingelogd blijven');
    $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'x'])
        ->assertSessionHasErrors(['email' => 'Deze combinatie van e-mailadres en wachtwoord is niet bij ons bekend.']);
    $this->post('/forgot-password', ['email' => ''])->assertSessionHasErrors(['email' => 'E-mailadres is verplicht.']);
    $this->get('/verify-email')->assertOk()->assertSee('Check je e-mail')->assertSee('Link opnieuw versturen');

    $mail = (string) (new VerifyEmailNotification())->toMail($person)->render();
    expect($mail)->toContain('Hallo!')->toContain('Bevestig je e-mailadres')->toContain('Met vriendelijke groet,')->toContain('Werkt de knop')
        ->not->toContain('Hello!')->not->toContain('Regards');

    $mail = (string) (new \Kopling\AuthEmailPassword\ResetPasswordNotification('token'))->toMail($person)->render();
    expect($mail)->toContain('Kies een nieuw wachtwoord')->toContain('Deze link verloopt over 60 minuten.');
});
