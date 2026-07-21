{{--
    The feed itself -- fills Community's chrome (see community/chrome.blade.php) with the
    tabs/poller/moment loop. The card feed queries real Moment rows -- see
    Kopling\Core\Content\Moment and Kopling\Core\Ux\Card\Card's own extensibility (Top/Body/
    Footer resolve kopling-core::card.header/.body/.footer, each with its own Context binding).
--}}
@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<\Kopling\Core\Content\Moment> $moments */
$moments = $context->getSubjectPaginator();
$since = optional($moments->first())->created_at?->toIso8601String() ?? now()->toIso8601String();
@endphp
<x-k::community.chrome>
    <x-k::portal.slot name="kopling-core::community.content-top" />

    {{-- $portal comes from InjectPortal's shared view global, not passed explicitly. --}}
    @include('kopling-core::community.poll', ['since' => $since])

    {{-- `gap-4 sm:gap-8` -- repeated between every card in the scroll, so this one's the
         cheapest, highest-impact trim of the three: pure inter-card spacing, no content of its
         own to compress. --}}
    <div id="moments-feed" class="flex flex-col gap-4 sm:gap-8">
        @foreach ($moments as $moment)
            @include('kopling-core::community.moment', ['moment' => $moment])
        @endforeach
    </div>


    <x-k::portal.slot name="kopling-core::community.content-bottom" />
</x-k::community.chrome>
