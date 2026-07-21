{{-- `hx-swap="none"`: idle by default, every 12s poll does nothing unless
     `LatestMomentsController::check()` overrides it with `HX-Reswap: outerHTML` to swap the
     new-moments banner in over it. `$oob`: composer's own post-response uses this same partial,
     id-targeted via `hx-swap-oob`, to advance `since` past a moment it just prepended directly --
     otherwise the poller's next tick would report that same moment as "new". --}}
<div
    id="moments-poller"
    hx-get="{{ route($portal->id.'/moments.latest', ['since' => $since]) }}"
    hx-trigger="every 12s"
    hx-swap="none"
    @if ($oob ?? false) hx-swap-oob="true" @endif
></div>
