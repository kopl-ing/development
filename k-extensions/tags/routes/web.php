<?php

use Illuminate\Support\Facades\Route;
use Kopling\Tags\Tag;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so "web", the prefix and the name prefix all come from the portal.
//
// The extension's own tag page -- everything under one tag. Deliberately its OWN route rather
// than a filter bolted onto core's feed: core's community feed is portal-scoped (poll/
// paginator), so filtering it would mean reaching into core. This reuses the base portal shell
// + core's card component instead.
Route::get('/tag/{slug}', function (string $slug) {
    $tag = Tag::where('slug', $slug)->firstOrFail();

    $moments = $tag->moments()->latest()->limit(50)->get();

    return view('kopling-tags::show', ['tag' => $tag, 'moments' => $moments]);
})->name('tags.show');

// The search endpoint `<x-k::form.tag-input>` calls from the tag picker (see `views/components/
// select.blade.php`) -- fulfils that component's own contract (JSON array of {id, label}
// pairs, matched by tag-input-tagify.js directly onto Tagify's own whitelist item shape).
// Capped at 5 regardless of how many tags exist, matching what a picker should ever show at
// once; called again with an empty `q` on focus, so an empty query still returns *something*
// (alphabetically first 5) rather than nothing. `auth`-gated since only a signed-in person ever
// sees the compose form this feeds.
Route::middleware('auth')->get('/_tags/search', function () {
    $query = trim((string) request()->query('q', ''));

    $tags = Tag::query()
        ->when($query !== '', fn ($builder) => $builder->where('name', 'like', '%'.$query.'%'))
        ->orderBy('name')
        ->limit(5)
        ->get();

    return response()->json($tags->map(fn (Tag $tag) => [
        'id' => $tag->id,
        'label' => $tag->name,
    ])->values());
})->name('tags.search');
