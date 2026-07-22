<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Kopling\Core\Authentication\Controller\LoginController;
use Kopling\Core\Authentication\Controller\RegistrationController;

Route::get('/', IndexController::class)->name('community');

// `_xhr/{extension-id}/...` -- pure htmx/AJAX action targets, never a page a person navigates
// to directly; see decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped
// path prefix". `theme.set` is the one exception on this page -- a plain, non-htmx `<form>`
// submission (see theme-switcher.blade.php), so it stays on its own real path.
Route::get('_xhr/kopling-core/moments/latest', [LatestMomentsController::class, 'check'])->name('moments.latest');
Route::get('_xhr/kopling-core/moments/load', [LatestMomentsController::class, 'load'])->name('moments.load');
Route::get('_xhr/kopling-core/icon-search', IconSearchController::class)->name('icon-search');

Route::post('theme', ThemeController::class)->name('theme.set');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.attempt');
    Route::get('register', [RegistrationController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegistrationController::class, 'register'])->name('register.attempt');
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
