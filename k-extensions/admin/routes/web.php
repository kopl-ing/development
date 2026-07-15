<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Admin\Controllers\SettingsController;

// Required inside the Admin portal's own Route::group() (see Extension::extendsPortals()), so
// it inherits the portal's prefix/name/"web"+"can:kopling-admin::access-admin" middleware.
// "manage-settings" is a second, more granular permission layered on top -- viewing/changing
// site configuration is a distinct capability from merely being let into the admin panel, same
// granular-not-a-flag philosophy every other Permission in this codebase already follows.
Route::middleware('can:kopling-admin::manage-settings')->group(function () {
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('settings', [SettingsController::class, 'store'])->name('settings.store');
    Route::post('settings/{id}/toggle', [SettingsController::class, 'toggle'])->name('settings.toggle');
});

// The bare Portal path ("/admin") itself has no page of its own yet -- settings is the only
// thing in here today, so land there rather than 404ing. Ungated beyond the Portal's own
// "access-admin" (inherited above): the redirect target re-checks "manage-settings" on arrival,
// no need to duplicate that check just to decide where to bounce someone.
Route::get('/', fn () => redirect()->route('kopling-admin::admin/settings'))->name('index');
