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
    // Toggle the viewer's reaction for one emoji on one reactable -- a Moment, or (once
    // discussions is installed) a Reply -- then re-render the rail (the primary swap target
    // every button posting here uses, hx-swap="outerHTML") plus the "Latest reactions" strip as
    // an out-of-band swap, so a chip's own remove button (`words.blade.php`, posts here too,
    // same delete-by-person+emoji path, word or not) actually disappears too, not just whichever
    // fragment happened to be the caller's own target. {type} must already be a registered
    // morph-map alias (Reaction::resolveReactable() 404s on anything else, never trusting a raw
    // class name from user input -- see that method's own docblock). The "auth" middleware
    // guarantees an actor, so Auth::user() is never null past here.
    // `_xhr/{extension-id}/...` -- htmx-only action targets, never a page on their own; see
    // decisions.md, "XHR/htmx-action endpoints get a dedicated, extension-scoped path prefix".
    Route::post('/_xhr/kopling-reactions/{type}/{id}', function (string $type, string $id) {
        $actor = Auth::user();
        $reactable = Reaction::resolveReactable($type, $id);

        $emoji = (string) request()->input('emoji', '');
        abort_unless(in_array($emoji, Reaction::PALETTE, true), 422);

        $existing = $reactable->reactions()
            ->where('person_id', $actor->id)
            ->where('emoji', $emoji)
            ->first();

        $existing
            ? $existing->delete()
            : $reactable->reactions()->create([
                'person_id' => $actor->id,
                'emoji' => $emoji,
            ]);

        return view('kopling-reactions::components.toggle-response', [
            'context' => new Context(subject: $reactable, actor: $actor),
        ]);
    })->name('reactions.toggle');

    // Toggle the viewer's vote for one emoji on one moment -- Moment-only, since voting is
    // configured per *tag* and a Reply carries no tags (see Reaction::voteConfigFor()'s own
    // docblock), so this keeps its own Moment-typed route-model-bound parameter rather than
    // going through the generic {type}/{id} resolution above -- validates against that moment's
    // own tags' configured vote emoji, not the generic Reaction::PALETTE. Same find-or-
    // create/delete toggle, same `reactions` table/row shape -- voting is not a distinct
    // storage concept, just a gated subset of the same emoji-reaction mechanism. The `/moment/`
    // segment (rather than a bare `/_reactions/{moment}/vote`) keeps this from ever structurally
    // colliding with the generic toggle route above, which is also shaped `_reactions/{x}/{y}`.
    Route::post('/_xhr/kopling-reactions/moment/{moment}/vote', function (Moment $moment) {
        $actor = Auth::user();

        $emoji = (string) request()->input('emoji', '');
        $configured = array_column(Reaction::voteConfigFor($moment), 'emoji');
        abort_unless(in_array($emoji, $configured, true), 422);

        $existing = $moment->reactions()
            ->where('person_id', $actor->id)
            ->where('emoji', $emoji)
            ->first();

        $existing
            ? $existing->delete()
            : $moment->reactions()->create([
                'person_id' => $actor->id,
                'emoji' => $emoji,
            ]);

        return view('kopling-reactions::components.vote', [
            'context' => new Context(subject: $moment, actor: $actor),
        ]);
    })->name('reactions.vote');

    // Add (or update) the viewer's reaction on one reactable from the picker modal: an emoji
    // plus an OPTIONAL short word. Then re-render the "Latest reactions" strip; the response
    // also carries the rail back out-of-band so its counts stay in sync. updateOrCreate keeps
    // it the same one-per-(reactable,person,emoji) row whether or not it already existed as a
    // plain toggle. The word is optional so this one endpoint serves both the modal's "emoji
    // only" and "emoji + word" cases (an empty word stores null -- the strip only lists worded
    // ones).
    Route::post('/_xhr/kopling-reactions/{type}/{id}/word', function (string $type, string $id) {
        $actor = Auth::user();
        $reactable = Reaction::resolveReactable($type, $id);

        $emoji = (string) request()->input('emoji', '');
        abort_unless(in_array($emoji, Reaction::PALETTE, true), 422);

        $word = trim((string) request()->input('word', ''));
        abort_if(mb_strlen($word) > Reaction::WORD_MAX, 422);

        $reactable->reactions()->updateOrCreate(
            ['person_id' => $actor->id, 'emoji' => $emoji],
            ['word' => $word === '' ? null : $word],
        );

        return view('kopling-reactions::components.words-response', [
            'context' => new Context(subject: $reactable, actor: $actor),
        ]);
    })->name('reactions.word');
});
