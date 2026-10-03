<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\AuthEmailPassword\Controllers\PasswordResetController;
use Kopling\AuthEmailPassword\Controllers\VerificationController;
use Kopling\AuthEmailPassword\Extension;

Route::middleware('guest')->group(function () {
    Route::get(Extension::verificationPath(), [VerificationController::class, 'notice'])->name('verification.notice');
    Route::post(Extension::verificationPath(), [VerificationController::class, 'resend'])
        ->middleware('throttle:3,1,kopling-verification-resend')
        ->name('verification.resend');

    $path = Extension::passwordResetPath();

    Route::get($path, [PasswordResetController::class, 'request'])->name('password.request');
    Route::post($path, [PasswordResetController::class, 'email'])
        ->middleware('throttle:5,1,kopling-password-email')
        ->name('password.email');
    Route::get($path.'/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post($path.'/{token}', [PasswordResetController::class, 'update'])
        ->middleware('throttle:5,1,kopling-password-reset')
        ->name('password.update');
});

// Outside `guest`: the link may be opened while someone else is signed in on this browser.
Route::get(Extension::verificationPath().'/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1,kopling-verification-verify'])
    ->name('verification.verify');
