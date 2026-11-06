<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\OAuth\Controllers\AdminController;

Route::middleware('can:kopling-oauth::manage-oauth')->prefix('oauth')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('oauth');
    Route::post('keys', [AdminController::class, 'generateKeys'])->name('oauth.keys');
    Route::post('clients/{client}/revoke', [AdminController::class, 'revokeClient'])->whereUuid('client')->name('oauth.clients.revoke');
    Route::post('tokens/{token}/revoke', [AdminController::class, 'revokeToken'])->name('oauth.tokens.revoke');
});
