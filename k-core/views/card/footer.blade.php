{{--
    `items-center` overrides `.card-actions`' own `flex-start` default -- wrong once two entries
    differ in height (reactions' `vote` vs. its `rail`). `flex-nowrap overflow-x-auto` keeps this
    one row, always: a wide entry scrolls internally instead of wrapping, which is what lets a
    trailing `ml-auto` entry (`engage`/`quote-op`) reliably pin to the row's end.
--}}
@if ($entries->isNotEmpty())
    <div class="card-actions flex-nowrap items-center overflow-x-auto px-4 py-2 sm:px-6 sm:py-4">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
