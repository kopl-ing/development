<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Kopling\Core\Content\Moment;
use Kopling\Core\Ux\Context;
use Kopling\Reactions\Reaction;

// Wrapped in the "web" group the same way k-core/src/routes/web.php and the example
// extension do -- loadRoutesFrom() only requires the file, it doesn't apply middleware.
Route::middleware('web')->group(function () {
    // Toggle the viewer's reaction for one emoji on one moment, then re-render the rail so
    // htmx can swap it in place (hx-swap="outerHTML"). A guest hitting this aborts 401,
    // which core's RedirectHtmxUnauthenticated turns into a login redirect for htmx.
    Route::post('/_reactions/{moment}', function (Moment $moment) {
        $actor = Auth::user();
        abort_unless($actor !== null, 401);

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
});
