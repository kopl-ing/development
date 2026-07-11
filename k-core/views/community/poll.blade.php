{{--
    Idle by default -- `hx-swap="none"` means every 12s poll does *nothing* to the DOM unless
    the server explicitly overrides that for a given response. `since` stays fixed at
    whatever it was set to here for as long as this exact element sits idle -- it only ever
    needs to advance once something's actually loaded (see loaded.blade.php), never during a
    "nothing new" poll (see LatestMomentsController::check() for why that's a bare 204, not a
    re-render of this same markup).

    When `check()` does find something, it sends `HX-Reswap: outerHTML` on that one response
    to override this element's declared "none" and swap the new-moments banner in over it
    (new-moments.blade.php) -- so this element only ever gets replaced on a real state
    change, never on an empty poll.
--}}
<div
    hx-get="{{ route($portal->id.'/moments.latest', ['since' => $since]) }}"
    hx-trigger="every 12s"
    hx-swap="none"
></div>
