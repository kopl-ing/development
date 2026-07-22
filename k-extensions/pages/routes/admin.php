<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Pages\Controllers\Admin\PageSectionsController;
use Kopling\Pages\Controllers\Admin\PageSectionTemplatesController;
use Kopling\Pages\Controllers\Admin\PagesController;

// Attached to Admin's own Portal (see Extension::extendsPortals()), so this inherits Admin's
// prefix/name/"can:kopling-admin::access-admin" middleware for free -- "manage-pages" is a
// second, more granular permission layered on top, same shape as Settings'
// "can:kopling-admin::manage-settings" group in kopling-admin's own routes/web.php.
Route::middleware('can:kopling-pages::manage-pages')->group(function () {
    Route::get('pages', [PagesController::class, 'index'])->name('pages');
    Route::get('pages/create', [PagesController::class, 'create'])->name('pages.create');
    Route::post('pages', [PagesController::class, 'store'])->name('pages.store');

    // scopeBindings(): {section} must actually belong to {page} -- Page::sections() already
    // matches the convention Laravel infers this from, so a section id from a different page
    // 404s here instead of silently updating the right row under the wrong page's URL.
    Route::scopeBindings()->group(function () {
        Route::get('pages/{page}/edit', [PagesController::class, 'edit'])->name('pages.edit');
        Route::post('pages/{page}', [PagesController::class, 'update'])->name('pages.update');
        Route::post('pages/{page}/delete', [PagesController::class, 'destroy'])->name('pages.destroy');

        Route::post('pages/{page}/sections', [PageSectionsController::class, 'store'])->name('pages.sections.store');
        Route::post('pages/{page}/sections/{section}', [PageSectionsController::class, 'update'])->name('pages.sections.update');
        Route::post('pages/{page}/sections/{section}/delete', [PageSectionsController::class, 'destroy'])->name('pages.sections.destroy');
        Route::post('pages/{page}/sections/{section}/move', [PageSectionsController::class, 'move'])->name('pages.sections.move');
    });
});

// A separate, more locked-down permission -- see Extension::permissions()'s own docblock on
// "manage-page-templates" for why this isn't folded into "manage-pages" above.
Route::middleware('can:kopling-pages::manage-page-templates')->group(function () {
    Route::get('section-templates', [PageSectionTemplatesController::class, 'index'])->name('section-templates');
    Route::post('section-templates', [PageSectionTemplatesController::class, 'store'])->name('section-templates.store');
    Route::post('section-templates/{template}', [PageSectionTemplatesController::class, 'update'])->name('section-templates.update');
    Route::post('section-templates/{template}/delete', [PageSectionTemplatesController::class, 'destroy'])->name('section-templates.destroy');
});
