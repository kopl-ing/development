<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Tags\Controllers\TagsController;

// Required inside the Admin portal's own Route::group() (see Extension::extendsPortals()), so
// it inherits "web" + the portal's own "can:kopling-admin::access-admin" gate. "manage-tags" is
// layered on top, same granular-permission shape every other admin route in this codebase uses
// (see GroupsController/PeopleController's own "manage-people" gate).
Route::middleware('can:kopling-tags::manage-tags')->group(function () {
    Route::get('tags', [TagsController::class, 'index'])->name('tags');
    Route::post('tags', [TagsController::class, 'store'])->name('tags.store');
    Route::post('tags/{tag}', [TagsController::class, 'update'])->name('tags.update');
    Route::post('tags/{tag}/delete', [TagsController::class, 'destroy'])->name('tags.destroy');
});
