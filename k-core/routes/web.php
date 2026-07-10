<?php

use Illuminate\Support\Facades\Route;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;

$portals = app(Manager::class)->portals();

$portals
    ->each(function (Portal $portal) {
        Route::prefix($portal->path)
            ->name($portal->id.'/')
            ->middleware($portal->middleware ?? 'web')
            ->middleware($portal->permission ? "can:$portal->permission" : null)
            ->group($portal->routes);
    });
