{{--
    The feed itself -- fills Community's chrome (see community/chrome.blade.php) with the
    tabs/poller/moment loop. The card feed queries real Moment rows -- see
    Kopling\Core\Content\Moment and Kopling\Core\Ux\Card\Card's own extensibility (Top/Body/
    Footer resolve kopling-core::card.header/.body/.footer, each with its own Context binding).
--}}
@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<\Kopling\Core\Content\Moment> $moments */
$moments = $context->getSubjectPaginator();
/** @var \Kopling\Core\Portal\Portal $portal */
$portal = $context->portal;
$since = optional($moments->first())->created_at?->toIso8601String() ?? now()->toIso8601String();
@endphp
<x-k::community.chrome>
    <div role="tablist" class="tabs tabs-border mb-4">
        <button role="tab" class="tab tab-active">Latest</button>
        <button role="tab" class="tab">Top</button>
        <button role="tab" class="tab">New</button>
    </div>

    <x-k::portal.slot name="kopling-core::community.content-top" />

    @include('kopling-core::community.poll', ['portal' => $portal, 'since' => $since])

    <div id="moments-feed" class="flex flex-col gap-4">
        @foreach ($moments as $moment)
            @include('kopling-core::community.moment', ['moment' => $moment, 'portal' => $portal])
        @endforeach
    </div>
</x-k::community.chrome>
