<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Kopling\Core\Extension\Manager;

readonly class InjectPortal
{
    public function __construct(protected readonly Manager $extension)
    {
    }

    /**
     * Shares the resolved Portal (or null) as a global view variable in addition to the
     * request attribute -- so every Blade view rendered during this request can read `$portal`
     * directly (e.g. the head partial choosing which extensions' css/js to link) without each
     * layout/component needing to thread it through explicitly or re-resolve it itself the way
     * `Ux\Community\Chrome` has to for routes that aren't grouped under any Portal.
     */
    public function handle(Request $request, Closure $next)
    {
        $portal = Str::before($request->route()?->getName(), '/');

        $portal = $this->extension->portals()->firstWhere('id', $portal);

        $request->attributes->set('portal', $portal);

        View::share('portal', $portal);

        return $next($request);
    }
}
