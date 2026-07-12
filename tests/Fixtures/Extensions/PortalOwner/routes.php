<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/owner-ping', fn () => 'owner-pong')->name('owner.ping');
