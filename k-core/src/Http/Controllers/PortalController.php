<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Kopling\Core\Extension\Manager;

class PortalController
{
    public function __construct(readonly protected Manager $manager)
    {
    }

    public function __invoke(Request $request)
    {
        $portal = $request->route()->getName();

        $portal = $this->manager->portals()->firstWhere('id', $portal);

        if (! $portal) {
            abort(404);
        }

        if ($portal->permission && ! $request->user()?->hasPermission($portal->permission)) {
            abort(403);
        }

        return view($portal->layout)
            ->with('portal', $portal);
    }
}
