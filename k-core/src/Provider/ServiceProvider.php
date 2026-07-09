<?php

declare(strict_types=1);

namespace Kopling\Core\Provider;

use Illuminate\Support\ServiceProvider as Provider;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\Manifest;

class ServiceProvider extends Provider
{
    public function register(): void
    {
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
