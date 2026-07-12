<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Kopling\Core\Content\Moment;
use Kopling\Core\Ux\Context;
use Kopling\Reactions\Reaction;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so "web", the prefix and the name prefix all come from the portal. Only "auth" is declared
// here -- it's what makes a guest (or a since-expired session) throw an AuthenticationException,
// which core's RedirectHtmxUnauthenticated turns into an HX-Redirect to login for an htmx
// request -- a plain abort(401) would never reach that handler.
Route::middleware('auth')->group(function () {
    // Toggle the viewer's reaction for one emoji on one moment, then re-render the rail so
    // htmx can swap it in place (hx-swap="outerHTML"). The "auth" middleware guarantees an
    // actor, so Auth::user() is never null past here.
    Route::post('/_reactions/{moment}', function (Moment $moment) {
        $actor = Auth::user();

        $emoji = (string) request()->input('emoji', '');
        abort_unless(in_array($emoji, Reaction::PALETTE, true), 422);

        $existing = Reaction::query()
            ->where('moment_id', $moment->id)
            ->where('person_id', $actor->id)
            ->where('emoji', $emoji)
            ->first();

        $existing
            ? $existing->delete()
            : Reaction::create([
                'moment_id' => $moment->id,
                'person_id' => $actor->id,
                'emoji' => $emoji,
            ]);

        return view('kopling-reactions::components.rail', [
            'context' => new Context(subject: $moment, actor: $actor),
        ]);
    })->name('reactions.toggle');

    // Attach a short word to the viewer's reaction (the demo's "Latest reactions" strip),
    // then re-render the strip; the response also carries the rail back out-of-band so its
    // counts stay in sync. updateOrCreate keeps it the same one-per-(moment,person,emoji)
    // row whether or not it already existed as a plain rail toggle.
    Route::post('/_reactions/{moment}/word', function (Moment $moment) {
        $actor = Auth::user();

        $emoji = (string) request()->input('emoji', '');
        abort_unless(in_array($emoji, Reaction::PALETTE, true), 422);

        $word = trim((string) request()->input('word', ''));
        abort_if($word === '' || mb_strlen($word) > Reaction::WORD_MAX, 422);

        Reaction::updateOrCreate(
            ['moment_id' => $moment->id, 'person_id' => $actor->id, 'emoji' => $emoji],
            ['word' => $word],
        );

        return view('kopling-reactions::components.words-response', [
            'context' => new Context(subject: $moment, actor: $actor),
        ]);
    })->name('reactions.word');
});
