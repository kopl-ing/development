<?php

use Illuminate\Support\Facades\Route;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;

$manager = app(Manager::class);
$portals = $manager->portals();

$portals
    ->each(function (Portal $portal) use ($manager) {

        $middleware = ['web'];

        if ($portal->permission) {
            $middleware[] = "can:$portal->permission";
        }

        // Every extension attaching routes to this Portal (see ExtendsPortals/PortalExtension)
        // gets required inside the same group, so each one inherits this Portal's prefix, name
        // prefix, and middleware for free -- including the extension that declared the Portal
        // itself, which goes through this exact same path, not a shortcut.
        Route::prefix($portal->path)
            ->name($portal->id.'/')
            ->middleware($middleware)
            ->group(function () use ($manager, $portal) {
                foreach ($manager->portalExtensions()->get($portal->id, collect()) as $extension) {
                    if ($extension->routes) {
                        require $extension->routes;
                    }
                }
            });
    });
