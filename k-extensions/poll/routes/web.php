<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Poll\Controllers\VoteController;

// `_xhr/{extension-id}/...` -- an htmx-only action target, never a page on its own; see
// decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped path prefix".
Route::middleware('auth')->group(function () {
    Route::post('/_xhr/kopling-poll/{poll}/vote', [VoteController::class, 'store'])->name('poll.vote');
});
