<?php

use Illuminate\Support\Facades\Route;
use Kopling\Core\Http\Controllers\HomeController;

// bootstrap/app.php doesn't declare a `web:` routes file, so the `web` middleware group
// (sessions, cookies, CSRF) isn't attached automatically -- apply it explicitly here.
Route::middleware('web')->group(function () {
    Route::get('/', HomeController::class)->name('home');
});

