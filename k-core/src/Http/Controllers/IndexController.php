<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Http\Request;
use Kopling\Core\Content\Event\QueryingMoments;
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
        if (! $request->attributes->has('portal')) {
            abort(404);
        }

        $portal = $request->attributes->get('portal');

        $query = Moment::query()->latest();
        event(new QueryingMoments($query));

        $context = new Context(
            subject: $query,
            portal: $portal,
            request: $request,
        );

        return view($portal->layout)
            ->with('context', $context);
    }
}
