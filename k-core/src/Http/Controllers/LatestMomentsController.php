<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;

/**
 * Backs the Community index's htmx polling loop (see community/{poll,new-moments,loaded}
 * .blade.php) -- plain polling, not SSE/a daemon, on purpose (see decisions.md): every
 * request here is a normal, fast, complete request/response, nothing held open.
 */
class LatestMomentsController
{
    public function __construct(readonly protected Manager $manager)
    {
    }

    /**
     * `community/moments.latest` -- the poller's own `hx-get` target. The poller declares
     * `hx-swap="none"` (see poll.blade.php): its default, every 12s, is to do *nothing* --
     * so "nothing new" returns a bare `204`, not a re-rendered view. htmx treats 204
     * specifically as "don't touch the DOM," which is both cheaper (no view render, no
     * markup to parse/diff on the client for a response that would've been identical anyway)
     * and correct: nothing about the poller actually needs to change while idle, since
     * `since` only ever needs to move once something real gets loaded (see `load()`).
     *
     * Deliberately not a `304` -- that status code belongs to HTTP's conditional-GET/caching
     * protocol (`If-None-Match`/`If-Modified-Since`, validated against a previous response's
     * `ETag`/`Last-Modified`), which answers "has *this* resource changed since the copy you
     * cached" for a stable, cacheable URL. This poll's URL isn't that: `since` is baked into
     * the query string, so it's a genuinely different question ("anything after *this*
     * timestamp?") on every request, not a revalidation of one cached resource. Building
     * real conditional-GET support to get a 304 here would mean the server reading
     * `If-None-Match` and comparing it itself -- solving the exact problem `204` already
     * solves, with strictly more moving parts and no extra benefit (a 304 can't carry a body
     * either way, so it wouldn't help the "found something" branch below regardless).
     *
     * When something *is* found, the response carries `HX-Reswap: outerHTML` -- a response
     * header that overrides the swap strategy the *client* declared, for this one response
     * only. That's what actually lets the banner replace the poller despite the poller's own
     * `hx-swap="none"` -- without it, htmx would honor "none" and silently do nothing even
     * though there's now a real banner to show.
     */
    public function check(Request $request): Response
    {
        $portal = $request->attributes->get('portal');
        $since = Carbon::parse($request->query('since'));

        $count = Moment::where('created_at', '>', $since)->count();

        if ($count === 0) {
            return response()->noContent();
        }

        return response()
            ->view('core::community.new-moments', [
                'portal' => $portal,
                'since' => $since->toIso8601String(),
                'count' => $count,
            ])
            ->header('HX-Reswap', 'outerHTML');
    }

    /**
     * `community/moments.load` -- the banner's `hx-get` target. Renders the actual new
     * moments (prepended into the feed via an OOB swap) and a resumed poller with `since`
     * advanced to the newest of what was just loaded.
     */
    public function load(Request $request): View
    {
        $portal = $request->attributes->get('portal');
        $since = Carbon::parse($request->query('since'));

        $moments = Moment::where('created_at', '>', $since)->latest()->get();

        return view('core::community.loaded', [
            'portal' => $portal,
            'moments' => $moments,
            'since' => optional($moments->first())->created_at?->toIso8601String() ?? $since->toIso8601String(),
        ]);
    }
}
