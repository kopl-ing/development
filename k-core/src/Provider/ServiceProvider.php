<?php

declare(strict_types=1);

namespace Kopling\Core\Provider;

use Illuminate\Support\ServiceProvider as Provider;

class ServiceProvider extends Provider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Ux/views', 'core');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
