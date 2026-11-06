<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Kopling\Core\Settings\EnabledExtensions;
use Kopling\Core\Settings\Settings;
use Kopling\OAuth\Bridge\ScopeRepository;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Passport;

/**
 * Wires Passport in for Kopling: routes, guard and views only while `kopling/oauth` is enabled (see decisions.md, "Infrastructure extensions wire Laravel packages through their own ServiceProvider").
 */
class ServiceProvider extends BaseServiceProvider
{
    public const ID = 'kopling-oauth';

    public const PROVIDER = 'kopling-oauth-people';

    public function register(): void
    {
        Passport::$registersRoutes = false;
        Passport::$deviceCodeGrantEnabled = false;
        Passport::$passwordGrantEnabled = false;
        Passport::$implicitGrantEnabled = false;

        config()->set('auth.providers.'.self::PROVIDER, ['driver' => 'kopling-oauth-people', 'model' => Person::class]);
        config()->set('auth.guards.api', ['driver' => 'passport', 'provider' => self::PROVIDER]);
        config()->set('passport.guard', 'web');

        Passport::useTokenModel(Token::class);
        $this->app->bind(PassportScopeRepository::class, ScopeRepository::class);
    }

    public function boot(): void
    {
        Auth::provider('kopling-oauth-people', fn ($app, array $config) => new PeopleProvider($app['hash'], $config['model']));

        if (! $this->enabled()) {
            return;
        }

        Passport::authorizationView('kopling-oauth::authorize');
        Passport::tokensExpireIn(now()->addMinutes(self::setting('access-token-minutes', 60)));
        Passport::refreshTokensExpireIn(now()->addDays(self::setting('refresh-token-days', 30)));
        Passport::personalAccessTokensExpireIn(now()->addDays(self::setting('personal-token-days', 365)));

        Route::post('oauth/token', [AccessTokenController::class, 'issueToken'])
            ->middleware('throttle:60,1')
            ->name(self::ID.'::token');
    }

    private function enabled(): bool
    {
        return EnabledExtensions::isEnabled(self::ID);
    }

    private static function setting(string $key, int $default): int
    {
        return max(1, (int) Settings::get(self::ID.'::'.$key, $default));
    }
}
