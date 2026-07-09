<?php

use Illuminate\Support\Facades\Route;

// loadRoutesFrom() just requires this file -- it doesn't apply middleware. Wrap routes in
// the "web" group here, the same way k-core/src/routes/web.php does, since bootstrap/app.php
// doesn't declare a `web:` routes file that would apply it automatically.
Route::middleware('web')->group(function () {
    Route::get('/_example/hello', fn () => view('kopling-example::hello'))->name('example.hello');
});
