<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Mcp\Controllers\AdminController;

Route::middleware('can:kopling-mcp::view-tool-calls')->get('mcp', [AdminController::class, 'index'])->name('mcp');
