<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RedirectHtmxUnauthenticated
{
    public function __invoke(AuthenticationException $e, Request $request): ?Response
    {
        if (! $request->hasHeader('HX-Request')) {
            return null;
        }

        return response('', 401)->header(
            'HX-Redirect',
            Route::has('login') ? route('login') : '/login',
        );
    }
}
