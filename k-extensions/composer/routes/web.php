<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Composer\Controllers\ComposerController;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so it inherits the portal's prefix, name prefix, and "web" middleware. Only the auth gate is
// declared here -- a guest POST throws AuthenticationException -> core's
// RedirectHtmxUnauthenticated turns it into an HX-Redirect to login, same as discussions' reply.
Route::post('/compose', [ComposerController::class, 'store'])
    ->middleware('auth')
    ->name('compose.store');
