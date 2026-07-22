<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Admin\Controllers\DrivesController;
use Kopling\Admin\Controllers\GroupsController;
use Kopling\Admin\Controllers\PeopleController;
use Kopling\Admin\Controllers\PortalsController;
use Kopling\Admin\Controllers\SettingsController;
use Kopling\Admin\Controllers\StorageMappingsController;

// Required inside the Admin portal's own Route::group() (see Extension::extendsPortals()), so
// it inherits the portal's prefix/name/"web"+"can:kopling-admin::access-admin" middleware.
// "manage-settings" is a second, more granular permission layered on top -- viewing/changing
// site configuration is a distinct capability from merely being let into the admin panel, same
// granular-not-a-flag philosophy every other Permission in this codebase already follows.
Route::middleware('can:kopling-admin::manage-settings')->group(function () {
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('settings', [SettingsController::class, 'store'])->name('settings.store');
    // `_xhr/{extension-id}/...` -- an htmx-only action target, never a page on its own; see
    // decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped path prefix".
    Route::post('_xhr/kopling-admin/settings/{id}/toggle', [SettingsController::class, 'toggle'])->name('settings.toggle');

    Route::get('drives', [DrivesController::class, 'index'])->name('drives');
    Route::post('drives', [DrivesController::class, 'store'])->name('drives.store');
    Route::post('drives/{drive}', [DrivesController::class, 'update'])->name('drives.update');
    Route::post('drives/{drive}/delete', [DrivesController::class, 'destroy'])->name('drives.destroy');

    // request_id/drive_id travel in the POST body, not as route-bound models -- see
    // StorageMappingsController's own docblock for why.
    Route::get('storage', [StorageMappingsController::class, 'index'])->name('storage');
    Route::post('storage', [StorageMappingsController::class, 'store'])->name('storage.store');
    Route::post('storage/delete', [StorageMappingsController::class, 'destroy'])->name('storage.destroy');

    // id travels in the POST body, not as a route-bound model -- see PortalsController's own
    // docblock for why.
    Route::get('portals', [PortalsController::class, 'index'])->name('portals');
    Route::post('portals', [PortalsController::class, 'update'])->name('portals.update');
    Route::post('portals/reset', [PortalsController::class, 'reset'])->name('portals.reset');
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

// "manage-permissions" is its own, more granular Core permission (declared since Core::permissions()'s
// original pass, never wired to a UI until now) -- deliberately not folded into "manage-people"
// above, same granular-not-a-flag philosophy as everywhere else: someone who can manage people
// isn't automatically trusted to grant/revoke permissions too.
Route::middleware('can:kopling-core::manage-permissions')->group(function () {
    Route::post('groups/{group}/permissions', [GroupsController::class, 'updatePermissions'])->name('groups.permissions');
});

// The bare Portal path ("/admin") itself has no page of its own yet -- settings is the only
// thing in here today, so land there rather than 404ing. Ungated beyond the Portal's own
// "access-admin" (inherited above): the redirect target re-checks "manage-settings" on arrival,
// no need to duplicate that check just to decide where to bounce someone.
Route::get('/', fn () => redirect()->route('kopling-admin::admin/settings'))->name('index');
