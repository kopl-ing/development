{{-- `hx-swap="none"`: idle by default, every 12s poll does nothing unless
     `LatestMomentsController::check()` overrides it with `HX-Reswap: outerHTML` to swap the
     new-moments banner in over it. --}}
<div
    hx-get="{{ route($portal->id.'/moments.latest', ['since' => $since]) }}"
    hx-trigger="every 12s"
    hx-swap="none"
></div>
