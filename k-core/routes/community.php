<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Support\Facades\Route;

Route::get('/', IndexController::class)->name('community');

Route::get('moments/latest', [LatestMomentsController::class, 'check'])->name('moments.latest');
Route::get('moments/load', [LatestMomentsController::class, 'load'])->name('moments.load');
