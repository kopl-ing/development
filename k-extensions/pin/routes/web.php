<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Pin\Controllers\PinController;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so "web", the prefix, and the name prefix all come from the portal. Only "auth" is declared
// here, same as reactions' own routes -- the "kopling-pin::pin-moments" gate is enforced inside
// PinController itself (via AuthorizesRequests), never trusting the Control-menu entry having
// hidden the action client-side.
Route::middleware('auth')->group(function () {
    Route::post('/_pin/{moment}', [PinController::class, 'store'])->name('pin.store');
    Route::post('/_pin/{moment}/unpin', [PinController::class, 'destroy'])->name('pin.destroy');
});
