<?php

declare(strict_types=1);

namespace Kopling\Core\Provider;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider as Provider;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\Manifest;
use Kopling\Core\Http\Exceptions\RedirectHtmxUnauthenticated;
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
    }

    public function boot(Manager $manager): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(new RedirectHtmxUnauthenticated());

        $this->loadMigrationsFrom(__DIR__.'/../migrations');
        $this->loadViewsFrom(__DIR__.'/../Ux/views', 'core');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        foreach ($manager->extensions() as $package => $extension) {
            $id = $manager->id($package);
            $conventions = $manager->conventions($package);

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
    }
}
