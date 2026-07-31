<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Widgets\Ux\PulseWidget;

// `_xhr/{extension-id}/...` -- htmx-only action target, never a page on its own; see
// decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped path prefix".
Route::get('/_xhr/kopling-widgets/pulse', fn () => (new PulseWidget)->render())->name('pulse.refresh');
