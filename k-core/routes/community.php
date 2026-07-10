<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Support\Facades\Route;

Route::get('/', IndexController::class)->name('community');
