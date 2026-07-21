<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Poll\Controllers\VoteController;

Route::middleware('auth')->group(function () {
    Route::post('/_poll/{poll}/vote', [VoteController::class, 'store'])->name('poll.vote');
});
