<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's own default `unauthenticated()` handling redirects to a route literally named
 * "login" -- this app never defines one (auth-email-password's own is namespaced, like every
 * other route here, "kopling-core::community/login"), so left alone, any unauthenticated
 * request hitting an "auth"-gated route throws RouteNotFoundException instead of redirecting.
 * Handles both request shapes fully (never falls through to Laravel's default): an htmx request
 * gets a 401 carrying `HX-Redirect` (so htmx navigates the whole page instead of trying to swap
 * a fragment); a plain request gets a normal redirect response, same as Laravel's own
 * `redirect()->guest()` would if "login" existed.
 */
class RedirectUnauthenticated
{
    public function __invoke(AuthenticationException $e, Request $request): Response
    {
        $login = Route::has('kopling-core::community/login')
            ? route('kopling-core::community/login')
            : '/login';

        if ($request->hasHeader('HX-Request')) {
            return response('', 401)->header('HX-Redirect', $login);
        }

        return redirect()->guest($login);
    }
}
