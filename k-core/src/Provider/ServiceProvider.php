<?php

declare(strict_types=1);

namespace Kopling\Core\Provider;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider as Provider;
use Illuminate\Support\Str;
use Kopling\Core\Console\Commands\DiscoverExtensions;
use Kopling\Core\Console\Commands\ListExtensionRegistrations;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\Manifest;
use Kopling\Core\Http\Exceptions\RedirectHtmxUnauthenticated;
use Kopling\Core\Http\Middleware\InjectPortal;
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

        $this->app->singleton(Manager::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverExtensions::class,
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
        $this->loadViewsFrom(__DIR__.'/../Ux/views', 'core');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        $manager->listeners();
        $manager->relations();

        foreach ($manager->extensions() as $package => $extension) {
            $id = $manager->id($package);
            $conventions = $manager->conventions($package);

            if ($package !== 'core') {
                Blade::componentNamespace(Str::beforeLast($extension::class, '\\'), $id);
            }

            if (isset($conventions['migrations'])) {
                $this->loadMigrationsFrom($conventions['migrations']);
            }

            if (isset($conventions['views'])) {
                $this->loadViewsFrom($conventions['views'], $id);
            }

            if (isset($conventions['routes'])) {
                $this->loadRoutesFrom($conventions['routes'].'/web.php');
            }

            if (isset($conventions['lang'])) {
                $this->loadTranslationsFrom($conventions['lang'], $id);
            }

            // css/js conventions are exposed via Manager::conventions() but not yet linked
            // onto the page -- that needs the head-assets outlet mechanism, not built yet.
        }

        foreach ($manager->permissions() as $permission) {
            Gate::define($permission->id, function (Person $person, ...$args) use ($permission) {
                if (! $person->hasPermission($permission->id)) {
                    return false;
                }

                if ($permission->callback === null) {
                    return true;
                }

                return ($permission->callback)($person, ...$args);
            });
        }
    }
}
