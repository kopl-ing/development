<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Kopling\Core\Authentication\AuthSettings;
use Kopling\Core\Authentication\Controller\LoginController;
use Kopling\Core\Authentication\Controller\RegistrationController;

Route::get('/', IndexController::class)->name('community');

// Reachable by anyone, signed in or not -- EnforceSanctions logs a sanctioned person out before
// redirecting here (see that middleware's own docblock), so by the time this loads they're
// already a guest again.
Route::get('access-blocked', SanctionEnforcementController::class)->name('access-blocked');

// `_xhr/{extension-id}/...` -- pure htmx/AJAX action targets, never a page a person navigates
// to directly; see decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped
// path prefix". `theme.set` is the one exception on this page -- a plain, non-htmx `<form>`
// submission (see theme-switcher.blade.php), so it stays on its own real path.
Route::get('_xhr/kopling-core/moments/latest', [LatestMomentsController::class, 'check'])->name('moments.latest');
Route::get('_xhr/kopling-core/moments/load', [LatestMomentsController::class, 'load'])->name('moments.load');
Route::get('_xhr/kopling-core/icon-search', IconSearchController::class)->name('icon-search');

Route::post('theme', ThemeController::class)->name('theme.set');

Route::middleware('guest')->group(function () {
    Route::get(AuthSettings::loginPath(), [LoginController::class, 'showLoginForm'])->name('login');
    Route::post(AuthSettings::loginPath(), [LoginController::class, 'login'])->name('login.attempt');
    Route::get(AuthSettings::registrationPath(), [RegistrationController::class, 'showRegistrationForm'])->name('register');
    Route::post(AuthSettings::registrationPath(), [RegistrationController::class, 'register'])
        ->middleware('throttle:10,60,kopling-registration')
        ->name('register.attempt');
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('settings', [AccountSettingsController::class, 'edit'])->name('settings');
    Route::post('settings', [AccountSettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/preferences', [AccountSettingsController::class, 'updatePreferences'])->name('settings.preferences');
});
