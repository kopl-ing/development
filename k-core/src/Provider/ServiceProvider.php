<?php

declare(strict_types=1);

namespace Kopling\Core\Provider;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider as Provider;
use Illuminate\Support\Str;
use Kopling\Core\Console\Commands\CacheRegistrations;
use Kopling\Core\Console\Commands\DisableExtension;
use Kopling\Core\Console\Commands\DiscoverExtensions;
use Kopling\Core\Console\Commands\EnableExtension;
use Kopling\Core\Console\Commands\ListExtensionRegistrations;
use Kopling\Core\Console\Commands\ListExtensions;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\Manifest;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\Http\Exceptions\RedirectUnauthenticated;
use Kopling\Core\Http\Middleware\EnforceSanctions;
use Kopling\Core\Http\Middleware\InjectPortal;
use Kopling\Core\People\Guest;
use Kopling\Core\People\Person;

class ServiceProvider extends Provider
{
    public function register(): void
    {
        $this->app['config']->set('auth.providers.users.model', Person::class);

        $this->app->singleton(Manifest::class, function ($app) {
            return new Manifest(
                $app->make('files'),
                $app->basePath(),
                $app->bootstrapPath('cache/kopling-extensions.php'),
            );
        });

        $this->app->singleton(RegistrationCache::class, function ($app) {
            return new RegistrationCache(
                $app->bootstrapPath('cache/kopling-registrations.php'),
            );
        });

        $this->app->singleton(Manager::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverExtensions::class,
                CacheRegistrations::class,
                ListExtensionRegistrations::class,
                ListExtensions::class,
                EnableExtension::class,
                DisableExtension::class,
                ...$this->app->make(Manager::class)->commands(),
            ]);
        }
    }

    public function boot(Manager $manager): void
    {
        // Without this, `route()`/`url()` resolve against the bound HTTP request's own host
        // when one exists, but fall back to `http://localhost` in any context with none (a real
        // `queue:work` worker process, an `artisan` command) -- silently wrong the moment
        // anything outside a request needs to mint a URL, e.g. `activitypub`'s own queued
        // delivery jobs signing a `/ap/people/{id}` URI meant to be publicly reachable.
        URL::forceRootUrl(config('app.url'));

        $this->app->make(ExceptionHandler::class)->renderable(new RedirectUnauthenticated());

        /** @var Kernel|\Illuminate\Foundation\Http\Kernel $http */
        $http = $this->app->make(Kernel::class);

        // Root install owns no code, not even bootstrap/app.php config -- Core behaves like any
        // other Laravel package, wiring itself up entirely from its own ServiceProvider. This
        // must run after the `$this->app->make(Kernel::class)` call above: resolving the Kernel
        // for the first time is what triggers Laravel's own `ApplicationBuilder::withMiddleware()`
        // to register its default `redirectGuestsTo(fn () => route('login'))` (via
        // `afterResolving`) -- set any earlier and that default overwrites it right back.
        // `Authenticate::redirectTo()` calls `route('login')` directly, at throw time, before
        // any exception handler (including RedirectUnauthenticated above) ever runs; this app has
        // no route literally named "login" (auth-email-password's own is namespaced, like every
        // route here). `redirectUsing()` is the same static hook
        // `Middleware::redirectGuestsTo()` (a bootstrap/app.php-only API) calls internally --
        // calling it directly here reaches the same fix without touching the skeleton.
        Authenticate::redirectUsing(
            fn () => Route::has('kopling-core::community/login') ? route('kopling-core::community/login') : '/login'
        );
        $http->appendMiddlewareToGroup('web', InjectPortal::class);
        // The per-request "is this person still allowed to be here" check -- see that
        // middleware's own docblock. Order relative to InjectPortal doesn't matter: this reads
        // only Auth::user(), already resolvable via the session as soon as StartSession has run
        // earlier in this same 'web' group, regardless of where either sits within it.
        $http->appendMiddlewareToGroup('web', EnforceSanctions::class);

        Blade::componentNamespace('Kopling\\Core\\Ux', 'k');

        $this->loadMigrationsFrom(__DIR__.'/../migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/assets.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        $manager->listeners();
        $manager->models();

        foreach ($manager->extensions() as $package => $extension) {
            $id = $manager->id($package);
            $conventions = $manager->conventions($package);

            if ($package !== 'kopling/core') {
                Blade::componentNamespace(Str::beforeLast($extension::class, '\\'), $id);
            }

            if (isset($conventions['migrations'])) {
                $this->loadMigrationsFrom($conventions['migrations']);
            }

            if (isset($conventions['views'])) {
                $this->loadViewsFrom($conventions['views'], $id);
            }

            if (isset($conventions['lang'])) {
                $this->loadTranslationsFrom($conventions['lang'], $id);
            }
        }

        // Unlike models()/listeners() above, this can safely run after the lang-loading loop --
        // nothing before this point needs its morph-map side effect yet (see
        // AggregatesModerationTargets's own docblock) -- and a ModerationTarget's own label can
        // then use __() the same as a Permission's, instead of hitting the same
        // called-before-its-own-lang-file-is-loaded trap Portal labels avoid by never
        // translating at all (see Extension::portals()'s own docblock).
        $manager->moderationTargets();

        foreach ($manager->permissions() as $permission) {
            Gate::define($permission->id, function (?Person $person) use ($permission) {
                $person ??= new Guest();

                if ($permission->default) {
                    return true;
                }

                // A guest-only permission is never meaningfully "held" by a real Group grant --
                // it's a signed-out check, not a capability -- so a real Person never reaches
                // hasPermission() for it, even if something accidentally granted it to one of
                // their Groups (e.g. a blanket "grant every permission" seed script).
                if ($permission->allowsGuests) {
                    return $person instanceof Guest;
                }

                return $person->hasPermission($permission->id);
            });
        }
    }
}
