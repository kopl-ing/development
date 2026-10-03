<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Kopling\Core\Authentication\AuthSettings;
use Kopling\Core\People\Person;
use Kopling\Core\Settings\Settings;

function reloadRoutes($app): void
{
    $router = $app['router'];
    $router->setRoutes(new RouteCollection());
    require base_path('k-core/routes/assets.php');
    require base_path('k-core/routes/web.php');
    $router->getRoutes()->refreshNameLookups();
}

function signUp($test, array $overrides = [])
{
    return $test->post('/'.AuthSettings::registrationPath(), $overrides + [
        'name' => 'Sam',
        'email' => 'sam@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);
}

it('signs up straight away when verification is off, landing on the intended page', function () {
    Settings::set(\Kopling\AuthEmailPassword\Extension::VERIFICATION_REQUIRED, '0');
    $this->get('/')->assertSee('href="'.url('/register').'"', false);

    $this->withSession(['url.intended' => url('/settings')]);
    signUp($this)->assertRedirect(url('/settings'));

    expect(Person::where('email', 'sam@example.test')->exists())->toBeTrue();
});

it('closes the sign-up page, endpoint and link when sign-ups are turned off', function () {
    Settings::set(AuthSettings::REGISTRATION_ENABLED, '0');

    $this->get('/register')->assertNotFound();
    signUp($this)->assertNotFound();
    $this->get('/')->assertDontSee('href="'.url('/register').'"', false)->assertSee('href="'.url('/login').'"', false);
    expect(Person::where('email', 'sam@example.test')->exists())->toBeFalse();
});

it('serves sign-up on a custom path and sends people to a configured page afterwards', function () {
    Settings::set(AuthSettings::REGISTRATION_PATH, '/join/');
    Settings::set(AuthSettings::REDIRECT_PATH, 'welcome');
    Settings::set(\Kopling\AuthEmailPassword\Extension::VERIFICATION_REQUIRED, '0');
    reloadRoutes($this->app);

    $this->get('/join')->assertOk();
    $this->get('/register')->assertNotFound();

    $this->withSession(['url.intended' => url('/settings')]);
    signUp($this)->assertRedirect('/welcome');
    expect(session()->has('url.intended'))->toBeFalse();
});

it('falls back to the default path for anything that is not a plain path', function () {
    expect(AuthSettings::path('https://evil.example/x', 'register'))->toBe('register')
        ->and(AuthSettings::path('../admin', 'register'))->toBe('register')
        ->and(AuthSettings::path(' sign-up/now ', 'register'))->toBe('sign-up/now')
        ->and(AuthSettings::path(null, 'register'))->toBe('register');
});

it('throttles sign-up attempts', function () {
    foreach (range(1, 10) as $attempt) {
        signUp($this, ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    signUp($this, ['email' => 'not-an-email'])->assertTooManyRequests();
});
