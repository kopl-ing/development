<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Kopling\Core\Content\Moment;
use Kopling\Discussions\Reply;

Route::middleware('web')->group(function () {
    // The discussion page for one moment -- the moment itself plus its reply thread and a
    // composer. Its own route + page (reusing the base portal shell + core's card), the same
    // pattern the tags extension uses, rather than anything bolted onto core's feed.
    Route::get('/m/{moment}', function (Moment $moment) {
        return view('kopling-discussions::show', [
            'moment' => $moment,
            'replies' => Reply::forMoment($moment),
        ]);
    })->name('discussions.show');

    // Post a reply, then return just the new reply so htmx can append it to the thread
    // (hx-swap="beforeend"). Guests abort 401 -> core's RedirectHtmxUnauthenticated.
    Route::post('/m/{moment}/reply', function (Moment $moment) {
        $actor = Auth::user();
        abort_unless($actor !== null, 401);

        $body = trim((string) request()->input('body', ''));
        abort_if($body === '', 422);

        $reply = Reply::create([
            'moment_id' => $moment->id,
            'person_id' => $actor->id,
            'body' => $body,
        ]);
        $reply->setRelation('person', $actor);

        return view('kopling-discussions::partials.reply', ['reply' => $reply]);
    })->name('discussions.reply');
});
