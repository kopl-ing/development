<?php

use Illuminate\Support\Facades\Route;
use Kopling\Discussions\Controllers\DiscussionController;

Route::middleware('web')->group(function () {
    Route::get('/m/{moment}', [DiscussionController::class, 'show'])
        ->name('discussions.show');
    Route::post('/m/{moment}/reply', [DiscussionController::class, 'reply'])
        ->name('discussions.reply');
});
