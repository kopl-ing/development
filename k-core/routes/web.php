<?php

use Illuminate\Support\Facades\Route;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;

$portals = app(Manager::class)->portals();

$portals
    ->each(function (Portal $portal) {

        $middleware = ['web'];

        if ($portal->permission) {
            $middleware[] = "can:$portal->permission";
        }

        Route::prefix($portal->path)
            ->name($portal->id.'/')
            ->middleware($middleware)
            ->group($portal->routes);
    });
