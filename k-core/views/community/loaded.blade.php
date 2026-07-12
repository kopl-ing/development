{{--
    LatestMomentsController::load's response: the direct/primary swap (replacing the banner
    that was clicked, via outerHTML) is a resumed poller; the OOB block prepends the actual
    new cards into the feed independently of that primary swap.
--}}
{{-- $portal comes from InjectPortal's shared view global, not passed explicitly. --}}
@include('kopling-core::community.poll', ['since' => $since])

<div id="moments-feed" hx-swap-oob="afterbegin">
    @foreach ($moments as $moment)
        @include('kopling-core::community.moment', ['moment' => $moment])
    @endforeach
</div>
