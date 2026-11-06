<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\OAuth\Controllers\ConnectedAppsController;

Route::middleware('auth')->prefix('settings/apps')->name('oauth.')->group(function () {
    Route::post('tokens', [ConnectedAppsController::class, 'createToken'])->middleware('throttle:10,60,oauth-tokens')->name('tokens.store');
    Route::post('tokens/{token}/revoke', [ConnectedAppsController::class, 'revokeToken'])->name('tokens.revoke');
    Route::post('{client}/revoke', [ConnectedAppsController::class, 'revokeClient'])->whereUuid('client')->name('clients.revoke');
});
