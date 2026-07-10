{{--
    LatestMomentsController::load's response: the direct/primary swap (replacing the banner
    that was clicked, via outerHTML) is a resumed poller; the OOB block prepends the actual
    new cards into the feed independently of that primary swap.
--}}
@include('core::community.poll', ['portal' => $portal, 'since' => $since])

<div id="moments-feed" hx-swap-oob="afterbegin">
    @foreach ($moments as $moment)
        @include('core::community.moment', ['moment' => $moment, 'portal' => $portal])
    @endforeach
</div>
