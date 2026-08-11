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

// `show`/`edit`/`update` back the edit-in-place flow (`ux/edit-control-entry.blade.php`) --
// `{reply}` alone is enough to resolve, no need to nest under `{moment}` the way creating one
// does. `update` is a plain POST with a URL suffix, not `Route::put()`/`@method('PUT')`,
// matching the no-spoofed-verbs convention every other mutating action in this codebase follows.
Route::get('/_xhr/kopling-discussions/replies/{reply}', [DiscussionController::class, 'showReply'])
    ->middleware('auth')
    ->name('discussions.reply.show');

Route::get('/_xhr/kopling-discussions/replies/{reply}/edit', [DiscussionController::class, 'editReply'])
    ->middleware('auth')
    ->name('discussions.reply.edit');

Route::post('/_xhr/kopling-discussions/replies/{reply}/update', [DiscussionController::class, 'updateReply'])
    ->middleware('auth')
    ->name('discussions.reply.update');
