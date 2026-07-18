<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Kopling\Core\Content\Moment;
use Kopling\Core\Ux\Context;
use Kopling\Reactions\Reaction;

// Required inside the Community portal's own Route::group() (see Extension::extendsPortals()),
// so "web", the prefix and the name prefix all come from the portal. Only "auth" is declared
// here -- it's what makes a guest (or a since-expired session) throw an AuthenticationException,
// which core's RedirectUnauthenticated turns into an HX-Redirect to login for an htmx
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

    // Toggle the viewer's vote for one emoji on one moment -- separate from the generic
    // toggle above so it validates against that moment's own tags' configured vote emoji
    // (Reaction::voteConfigFor), not the generic Reaction::PALETTE. Same find-or-
    // create/delete toggle, same `reactions` table/row shape -- voting is not a distinct
    // storage concept, just a gated subset of the same emoji-reaction mechanism.
    Route::post('/_reactions/{moment}/vote', function (Moment $moment) {
        $actor = Auth::user();

        $emoji = (string) request()->input('emoji', '');
        $configured = array_column(Reaction::voteConfigFor($moment), 'emoji');
        abort_unless(in_array($emoji, $configured, true), 422);

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

        return view('kopling-reactions::components.vote', [
            'context' => new Context(subject: $moment, actor: $actor),
        ]);
    })->name('reactions.vote');

    // Add (or update) the viewer's reaction from the picker modal: an emoji plus an OPTIONAL
    // short word. Then re-render the "Latest reactions" strip; the response also carries the
    // rail back out-of-band so its counts stay in sync. updateOrCreate keeps it the same
    // one-per-(moment,person,emoji) row whether or not it already existed as a plain toggle.
    // The word is optional so this one endpoint serves both the modal's "emoji only" and
    // "emoji + word" cases (an empty word stores null -- the strip only lists worded ones).
    Route::post('/_reactions/{moment}/word', function (Moment $moment) {
        $actor = Auth::user();

        $emoji = (string) request()->input('emoji', '');
        abort_unless(in_array($emoji, Reaction::PALETTE, true), 422);

        $word = trim((string) request()->input('word', ''));
        abort_if(mb_strlen($word) > Reaction::WORD_MAX, 422);

        Reaction::updateOrCreate(
            ['moment_id' => $moment->id, 'person_id' => $actor->id, 'emoji' => $emoji],
            ['word' => $word === '' ? null : $word],
        );

        return view('kopling-reactions::components.words-response', [
            'context' => new Context(subject: $moment, actor: $actor),
        ]);
    })->name('reactions.word');
});
