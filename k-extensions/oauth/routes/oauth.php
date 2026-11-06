<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

// Passport's own controllers on the oauth Portal, so the consent screen renders in Kopling's layout; the token endpoint lives outside `web` (ServiceProvider).
Route::get('authorize', [AuthorizationController::class, 'authorize'])->name('authorize');

Route::middleware('auth')->group(function () {
    Route::post('authorize', [ApproveAuthorizationController::class, 'approve'])->name('approve');
    Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])->name('deny');
});
