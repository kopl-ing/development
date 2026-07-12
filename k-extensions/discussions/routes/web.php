<?php

use Illuminate\Support\Facades\Route;
use Kopling\Discussions\Controllers\DiscussionController;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so this already inherits its prefix, name prefix, and "web" + optional `can:` middleware --
// no need to declare middleware here the way a bare directory-convention route file would.
Route::get('/m/{moment}', [DiscussionController::class, 'show'])
    ->name('discussions.show');
Route::post('/m/{moment}/reply', [DiscussionController::class, 'reply'])
    ->name('discussions.reply');
