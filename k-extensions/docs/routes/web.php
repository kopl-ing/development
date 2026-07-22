<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Docs\Controllers\DocsController;

// Ungated -- this Portal declares no permission (see Extension::portals()), a public docs site
// by design. `{slug}` allows slashes (front-matter-derived slugs are hierarchical, e.g.
// "extending/portals"), a plain Laravel route segment doesn't match those by default.
Route::get('/', [DocsController::class, 'index'])->name('index');
Route::get('/{slug}', [DocsController::class, 'show'])->name('show')->where('slug', '.*');
