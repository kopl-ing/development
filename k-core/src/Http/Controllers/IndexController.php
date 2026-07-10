<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;

class IndexController
{
    public function __construct(readonly protected Manager $manager)
    {
    }

    public function __invoke(Request $request)
    {
        $portal = Str::before($request->route()->getName(), '/');

        $portal = $this->manager->portals()->firstWhere('id', $portal);

        if (! $portal) {
            abort(404);
        }

        $context = new Context(
            subject: Moment::query()->latest()->paginate(),
            portal: $portal,
            request: $request,
        );

        return view($portal->layout)
            ->with('context', $context);
    }
}
