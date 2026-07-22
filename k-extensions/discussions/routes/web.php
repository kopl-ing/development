<?php

use Illuminate\Support\Facades\Route;
use Kopling\Discussions\Controllers\DiscussionController;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so this already inherits its prefix, name prefix, and "web" + optional `can:` middleware --
// no need to declare middleware here the way a bare directory-convention route file would.
Route::get('/m/{moment}', [DiscussionController::class, 'show'])
    ->name('discussions.show');

// `_xhr/{extension-id}/...` -- an htmx-only action target, never a page on its own (unlike
// `discussions.show` above, a real page); see decisions.md, "XHR/htmx-action endpoints get a
// dedicated, extension-scoped path prefix".
Route::post('/_xhr/kopling-discussions/m/{moment}/reply', [DiscussionController::class, 'reply'])
    ->name('discussions.reply');
