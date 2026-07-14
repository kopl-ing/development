<?php

declare(strict_types=1);

namespace Kopling\Core\Provider;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider as Provider;
use Illuminate\Support\Str;
use Kopling\Core\Console\Commands\CacheRegistrations;
use Kopling\Core\Console\Commands\DiscoverExtensions;
use Kopling\Core\Console\Commands\ListExtensionRegistrations;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\Manifest;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\Http\Exceptions\RedirectHtmxUnauthenticated;
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
                ...$this->app->make(Manager::class)->commands(),
            ]);
        }
    }

    public function boot(Manager $manager): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(new RedirectHtmxUnauthenticated());

        /** @var Kernel|\Illuminate\Foundation\Http\Kernel $http */
        $http = $this->app->make(Kernel::class);
        $http->appendMiddlewareToGroup('web', InjectPortal::class);

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
