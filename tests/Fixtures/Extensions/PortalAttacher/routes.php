<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/attacher-ping', fn () => 'attacher-pong')->name('attacher.ping');
