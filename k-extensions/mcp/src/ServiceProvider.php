<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Kopling\Core\Settings\EnabledExtensions;
use Kopling\Core\Settings\Settings;
use Kopling\Mcp\Controllers\DiscoveryController;
use Kopling\Mcp\Controllers\RegisterClientController;
use Kopling\Mcp\Http\RequireMcpAccess;
use Kopling\OAuth\Scope;
use Kopling\OAuth\Scopes;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;

/**
 * Serves `/mcp` and its OAuth discovery while `kopling/mcp` is enabled (see decisions.md, "Infrastructure extensions wire Laravel packages through their own ServiceProvider").
 */
class ServiceProvider extends BaseServiceProvider
{
    public const ID = 'kopling-mcp';

    public function register(): void
    {
        $this->app->singleton(Registry::class);
        $this->app->scoped(Audit::class);
    }

    public function boot(): void
    {
        if (! EnabledExtensions::isEnabled(self::ID) || ! EnabledExtensions::isEnabled(\Kopling\OAuth\ServiceProvider::ID)) {
            return;
        }

        self::registerScopes(app(Registry::class));

        config()->set('mcp.redirect_domains', self::redirectDomains());

        Mcp::web('mcp', Server::class)
            ->middleware(['auth:api', RequireMcpAccess::class, 'throttle:'.self::setting('rate-limit', 60).',1,mcp'])
            ->name(self::ID.'::endpoint');

        Route::get('.well-known/oauth-protected-resource', [DiscoveryController::class, 'protectedResource'])->name(self::ID.'::protected-resource');
        Route::get('.well-known/oauth-protected-resource/{path}', [DiscoveryController::class, 'protectedResource'])
            ->where('path', '.*')
            // Laravel MCP's `AddWwwAuthenticateHeader` looks for exactly this name to point clients here.
            ->name('mcp.oauth.protected-resource.nested');
        Route::get('.well-known/oauth-authorization-server', [DiscoveryController::class, 'authorizationServer'])->name(self::ID.'::authorization-server');
        Route::get('.well-known/oauth-authorization-server/{path}', [DiscoveryController::class, 'authorizationServer'])->where('path', '.*');
        Route::post('oauth/register', RegisterClientController::class)->middleware('throttle:10,60,mcp-register')->name(self::ID.'::register');
    }

    /**
     * Every MCP scope needs `use-mcp` to grant; a client that asks for no scope gets all of them (each still filtered by permission).
     */
    public static function registerScopes(Registry $registry): void
    {
        $scopes = [Access::SCOPE => __('kopling-mcp::messages.scope'), ...$registry->scopes()];
        foreach ($scopes as $id => $description) {
            Scopes::add(new Scope($id, $description, Access::PERMISSION));
        }
        Passport::setDefaultScope(array_keys($scopes));
    }

    /**
     * @return list<string>
     */
    private static function redirectDomains(): array
    {
        $lines = preg_split('/\R/', (string) Settings::get(self::ID.'::redirect-domains', '*')) ?: [];

        return array_values(array_filter(array_map('trim', $lines)));
    }

    private static function setting(string $key, int $default): int
    {
        return max(1, (int) Settings::get(self::ID.'::'.$key, $default));
    }
}
