<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Composer\Controllers\ComposerController;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so it inherits the portal's prefix, name prefix, and "web" middleware. Only the auth gate is
// declared here -- a guest POST throws AuthenticationException -> core's
// RedirectUnauthenticated turns it into an HX-Redirect to login, same as discussions' reply.
//
// `_xhr/{extension-id}/...` -- an htmx-only action target, never a page on its own; see
// decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped path prefix".
Route::post('/_xhr/kopling-composer/compose', [ComposerController::class, 'store'])
    ->middleware('auth')
    ->name('compose.store');

// `show`/`edit`/`update` back the edit-in-place flow (`ux/edit-control-entry.blade.php`) --
// `update` is a plain POST with a URL suffix, not `Route::put()`/`@method('PUT')`, matching the
// no-spoofed-verbs convention every other mutating action in this codebase already follows.
Route::get('/_xhr/kopling-composer/compose/{moment}', [ComposerController::class, 'show'])
    ->middleware('auth')
    ->name('compose.show');

Route::get('/_xhr/kopling-composer/compose/{moment}/edit', [ComposerController::class, 'edit'])
    ->middleware('auth')
    ->name('compose.edit');

Route::post('/_xhr/kopling-composer/compose/{moment}/update', [ComposerController::class, 'update'])
    ->middleware('auth')
    ->name('compose.update');
