<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Core\Http\Controllers\DistAssetController;
use Kopling\Core\Http\Controllers\ExtensionAssetController;

// Not grouped under any Portal -- an extension's css/js is looked up by key regardless of which
// Portal it's attached to (see Manager::extensionAssets()), so this route sits outside the
// per-Portal loop entirely.
Route::get('/_kopling/assets/{key}', ExtensionAssetController::class)->name('kopling-core::assets');

// A separate, directory-scoped route (not folded into the one above) -- a compiled dist bundle
// can `import` a sibling chunk Vite emitted alongside it, which the browser resolves relative to
// this URL, so entry and chunk need to share one directory prefix. `{filename}` can't contain
// `/` (Laravel's default segment regex already excludes it), and DistAssetController confines it
// to the one directory `{key}` names -- see its own docblock.
Route::get('/_kopling/assets/dist/{key}/{filename}', DistAssetController::class)->name('kopling-core::dist-assets');
