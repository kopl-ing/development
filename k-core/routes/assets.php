<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Core\Http\Controllers\ExtensionAssetController;

// Not grouped under any Portal -- an extension's css/js is looked up by key regardless of which
// Portal it's attached to (see Manager::extensionAssets()), so this route sits outside the
// per-Portal loop entirely.
Route::get('/_kopling/assets/{key}', ExtensionAssetController::class)->name('kopling-core::assets');
