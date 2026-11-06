<?php

declare(strict_types=1);

namespace Kopling\Mcp\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kopling\Mcp\Access;
use Kopling\OAuth\Person;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses the whole endpoint, not just single tools, without `use-mcp`, so losing the permission stops a still-valid token at once.
 */
class RequireMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $person = Auth::guard('api')->user();

        if (! Access::allows($person instanceof Person ? $person : null)) {
            return response()->json(['error' => 'insufficient_scope', 'error_description' => 'This token may not use MCP.'], 403)
                ->header('WWW-Authenticate', 'Bearer error="insufficient_scope", scope="'.Access::SCOPE.'"');
        }

        return $next($request);
    }
}
