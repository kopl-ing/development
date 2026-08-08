<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Moderation\Controllers\FlagController;
use Kopling\Moderation\Controllers\QueueController;
use Kopling\Moderation\Controllers\SanctionController;

// Required inside the Moderation portal's own Route::group() -- already gated by "web" +
// "can:kopling-moderation::moderate" via the Portal's own `permission` (see Extension::portals()).
// QueueController/FlagController::dismiss()/SanctionController re-check the same permission
// anyway, the same defense-in-depth every controller in this codebase applies regardless of
// route middleware.
Route::get('/', [QueueController::class, 'index'])->name('queue.index');
Route::post('/flags/{flag}/dismiss', [FlagController::class, 'dismiss'])->name('flag.dismiss');
Route::post('/sanctions/{person}', [SanctionController::class, 'store'])->name('sanction.store');
Route::post('/sanctions/{person}/lift', [SanctionController::class, 'lift'])->name('sanction.lift');
