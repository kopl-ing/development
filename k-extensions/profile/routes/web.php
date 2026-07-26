<?php

use Illuminate\Support\Facades\Route;
use Kopling\Profile\Controllers\ProfileController;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so this already inherits its prefix, name prefix, and "web" middleware.
Route::get('/p/{person}', [ProfileController::class, 'show'])
    ->name('profile.show');
