<?php

use Illuminate\Support\Facades\Route;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Http\Controllers\PortalController;
use Kopling\Core\Portal\Portal;

$portals = app(Manager::class)->portals();

$portals
    ->each(function (Portal $portal) {
        Route::get($portal->path, PortalController::class)
            ->middleware($portal->middleware ?? 'web')
            ->name($portal->id);
    });
