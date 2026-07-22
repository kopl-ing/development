<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Pages\Controllers\PageController;

// Ungated -- this Portal declares no permission (see Extension::portals()), a public
// marketing/static-page surface by design.
Route::get('/', [PageController::class, 'index'])->name('index');
Route::get('/{path?}', [PageController::class, 'show'])->name('show')->where('path', '.*');
