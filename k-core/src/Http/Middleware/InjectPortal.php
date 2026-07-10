<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kopling\Core\Extension\Manager;

readonly class InjectPortal
{
    public function __construct(protected readonly Manager $extension)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $portal = Str::before($request->route()?->getName(), '/');

        $portal = $this->extension->portals()->firstWhere('id', $portal);

        $request->attributes->set('portal', $portal);

        return $next($request);
    }
}
