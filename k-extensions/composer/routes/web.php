<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Composer\Controllers\ComposerController;

// Auth-gated: a guest POST throws AuthenticationException -> core's RedirectHtmxUnauthenticated
// turns it into an HX-Redirect to login, same as discussions' reply route.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/compose', [ComposerController::class, 'store'])->name('compose.store');
});
