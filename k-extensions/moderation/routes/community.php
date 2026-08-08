<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Moderation\Controllers\FlagController;
use Kopling\Moderation\Controllers\ModerationController;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so "web", the prefix, and the name prefix all come from the portal. Only "auth" is declared
// here -- the "kopling-moderation::moderate" gate is enforced inside each controller itself,
// never trusting the Card control menu having hidden hide/unhide/delete client-side (same split
// pin/reactions already use). Reporting needs no permission at all, by design.
// `_xhr/{extension-id}/...` -- htmx-only action targets, never a page on their own; see
// decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped path prefix".
// "/delete", not a spoofed DELETE verb -- no existing route in this codebase uses `@method
// ('DELETE')`/`Route::delete()`, every destructive-ish action (Pin's unpin, this extension's own
// hide/unhide) is a plain POST with a URL suffix; matching that beats introducing a new pattern.
Route::middleware('auth')->group(function () {
    Route::post('/_xhr/kopling-moderation/{type}/{id}', [FlagController::class, 'store'])->name('flag.store');
    Route::post('/_xhr/kopling-moderation/{type}/{id}/hide', [ModerationController::class, 'hide'])->name('flag.hide');
    Route::post('/_xhr/kopling-moderation/{type}/{id}/unhide', [ModerationController::class, 'unhide'])->name('flag.unhide');
    Route::post('/_xhr/kopling-moderation/{type}/{id}/delete', [ModerationController::class, 'destroy'])->name('flag.destroy');
});
