<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kopling\Core\Content\Event\QueryingMoments;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;

/**
 * Backs the Community index's htmx polling loop -- plain polling, not SSE/a daemon (see
 * decisions.md).
 */
class LatestMomentsController
{
    public function __construct(readonly protected Manager $manager)
    {
    }

    /**
     * The poller's `hx-get` target (`hx-swap="none"`, see poll.blade.php). "Nothing new" returns
     * a bare `204` (htmx's "don't touch the DOM" signal), not `304` -- `since` is a query param,
     * not a cacheable resource, so there's nothing to conditionally-GET revalidate. When
     * something *is* found, `HX-Reswap: outerHTML` overrides the poller's own declared
     * `hx-swap="none"` for this one response, letting the new-moments banner replace it.
     */
    public function check(Request $request): Response
    {
        $since = Carbon::parse($request->query('since'));

        $query = Moment::where('created_at', '>', $since);
        event(new QueryingMoments($query));

        $count = $query->count();

        if ($count === 0) {
            return response()->noContent();
        }

        return response()
            ->view('kopling-core::community.new-moments', [
                'since' => $since->toIso8601String(),
                'count' => $count,
            ])
            ->header('HX-Reswap', 'outerHTML');
    }

    /**
     * The banner's `hx-get` target -- renders the new moments plus a resumed poller with
     * `since` advanced to the newest of what was just loaded.
     */
    public function load(Request $request): View
    {
        $since = Carbon::parse($request->query('since'));

        $query = Moment::where('created_at', '>', $since);
        event(new QueryingMoments($query));

        $moments = $query->latest()->get();

        return view('kopling-core::community.loaded', [
            'moments' => $moments,
            'since' => optional($moments->first())->created_at?->toIso8601String() ?? $since->toIso8601String(),
        ]);
    }
}
