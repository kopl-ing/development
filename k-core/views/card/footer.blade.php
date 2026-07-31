{{--
    `items-center` overrides `.card-actions`' own `flex-start` default -- wrong once two entries
    differ in height (reactions' `vote` vs. its `rail`). `flex-nowrap overflow-x-auto` keeps this
    one row, always: a wide entry scrolls internally instead of wrapping, which is what lets a
    trailing `ml-auto` entry (`engage`/`quote-op`) reliably pin to the row's end.

    `min-w-0` (as a flex item of `Top`/`Body`/`Footer`'s shared `divide-y` wrapper -- itself a
    flex item of `.card`) is explicit rather than relied-on: `overflow-x-auto` is supposed to
    grant this row's own automatic flex-item minimum an exemption down to 0 per spec, but a pile
    of non-shrinking (`shrink-0`) reaction chips (see reactions' `words.blade.php`) has shown
    that exemption isn't reliably applied on mobile -- explicit `min-w-0` doesn't depend on it.
--}}
@if ($entries->isNotEmpty())
    <div class="card-actions flex-nowrap items-center overflow-x-auto px-4 py-2 sm:px-6 sm:py-4">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
