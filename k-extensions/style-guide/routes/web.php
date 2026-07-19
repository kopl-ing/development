<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Required inside the Style Guide portal's own Route::group() (see Extension::extendsPortals()),
// so it inherits the portal's prefix ("style-guide"), name prefix, and "web" +
// "can:kopling-style-guide::access-style-guide" middleware.
Route::get('/', fn () => view('kopling-style-guide::index'))->name('index');

// A tiny static search endpoint so the TagInput showcase below is genuinely interactive rather
// than a dead mock -- not a real search feature, just fixture data for the component to query.
Route::get('/tag-input-search', fn () => response()->json([
    ['id' => '1', 'label' => 'Design'],
    ['id' => '2', 'label' => 'Engineering'],
    ['id' => '3', 'label' => 'Community'],
]))->name('tag-input-search');
