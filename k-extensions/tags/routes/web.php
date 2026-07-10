<?php

use Illuminate\Support\Facades\Route;
use Kopling\Tags\Tag;

// Wrapped in the "web" group the same way k-core and the other extensions do.
Route::middleware('web')->group(function () {
    // The extension's own tag page -- everything under one tag. Deliberately its OWN route
    // rather than a filter bolted onto core's feed: core's community feed is portal-scoped
    // (poll/paginator), so filtering it would mean reaching into core. This reuses the base
    // portal shell + core's card component instead.
    Route::get('/tag/{slug}', function (string $slug) {
        $tag = Tag::where('slug', $slug)->firstOrFail();

        $moments = $tag->moments()->latest()->limit(50)->get();

        return view('kopling-tags::show', ['tag' => $tag, 'moments' => $moments]);
    })->name('tags.show');
});
