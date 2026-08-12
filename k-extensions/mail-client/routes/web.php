<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\MailClient\Controllers\AccountsController;
use Kopling\MailClient\Controllers\MailboxController;
use Kopling\MailClient\Controllers\MessagesController;

// Required inside kopling-mail-client::mail's own Route::group() (see Extension::extendsPortals()),
// so this already inherits its prefix, name prefix, "web" middleware, and the "access-mail" gate
// (Extension::portals()).
Route::get('/', [MailboxController::class, 'index'])->name('index');
Route::get('view/{view}', [MailboxController::class, 'smartView'])->name('view')->whereIn('view', ['flagged', 'sent']);
Route::get('accounts/{account}/folders/{folder}', [MailboxController::class, 'folder'])->name('folder');
Route::get('messages/{message}', [MessagesController::class, 'show'])->name('messages.show');

Route::get('accounts', [AccountsController::class, 'index'])->name('accounts');
Route::post('accounts', [AccountsController::class, 'store'])->name('accounts.store');
// POST, not DELETE -- no method-spoofing, matches Admin's own drives/portals/groups destroy routes.
Route::post('accounts/{account}/delete', [AccountsController::class, 'destroy'])->name('accounts.destroy');
