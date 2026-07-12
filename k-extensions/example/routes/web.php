<?php

use Illuminate\Support\Facades\Route;

// Required inside the target Portal's own Route::group() (see Extension::extendsPortals()), so
// this already inherits its prefix, name prefix, and "web" + optional `can:` middleware.
Route::get('/_example/hello', fn () => view('kopling-example::hello'))->name('example.hello');
