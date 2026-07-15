<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Admin\Controllers\GroupsController;
use Kopling\Admin\Controllers\PeopleController;
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

// "manage-people" belongs to Core (declared in Core::permissions()), not Admin -- referenced
// cross-extension here, so it stays fully qualified ("kopling-core::manage-people"), unlike
// Admin's own local permissions above which the Manager already prefixes for us.
Route::middleware('can:kopling-core::manage-people')->group(function () {
    Route::get('people', [PeopleController::class, 'index'])->name('people');
    Route::post('people/{person}/groups', [PeopleController::class, 'updateGroups'])->name('people.groups');

    Route::get('groups', [GroupsController::class, 'index'])->name('groups');
    Route::post('groups', [GroupsController::class, 'store'])->name('groups.store');
    Route::post('groups/{group}', [GroupsController::class, 'update'])->name('groups.update');
    Route::post('groups/{group}/delete', [GroupsController::class, 'destroy'])->name('groups.destroy');
});

// The bare Portal path ("/admin") itself has no page of its own yet -- settings is the only
// thing in here today, so land there rather than 404ing. Ungated beyond the Portal's own
// "access-admin" (inherited above): the redirect target re-checks "manage-settings" on arrival,
// no need to duplicate that check just to decide where to bounce someone.
Route::get('/', fn () => redirect()->route('kopling-admin::admin/settings'))->name('index');
