<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\Activitypub\Controllers\ActorController;
use Kopling\Activitypub\Controllers\InboxController;
use Kopling\Activitypub\Controllers\ObjectController;
use Kopling\Activitypub\Controllers\WebfingerController;
use Kopling\Activitypub\Middleware\VerifyHttpSignature;

// Required inside this Portal's own Route::group() (see Extension::extendsPortals()) -- "api"
// middleware (stateless, no session/CSRF) comes from the Portal itself, not declared here.
Route::get('/.well-known/webfinger', WebfingerController::class)->name('webfinger');

// Registered before the generic /ap/{type}/{id} below -- Person carries actor-only fields
// (inbox/outbox/keys) no other federatable object does, so it keeps its own dedicated route;
// Laravel matches route declaration order, so this literal "people" segment always wins over
// the wildcard for a two-segment /ap/... path.
Route::get('/ap/people/{person}', ActorController::class)->name('people.show');

// VerifyHttpSignature is route-level only, never Portal-wide -- every GET above stays
// unauthenticated. Both resolve to the same controller (see its own docblock).
Route::post('/ap/people/{person}/inbox', InboxController::class)
    ->middleware(VerifyHttpSignature::class)
    ->name('people.inbox');
Route::post('/ap/inbox', InboxController::class)
    ->middleware(VerifyHttpSignature::class)
    ->name('shared-inbox');

Route::get('/ap/{type}/{id}', ObjectController::class)->name('objects.show');
