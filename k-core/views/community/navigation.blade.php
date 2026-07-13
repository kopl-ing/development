@if ($surface === 'dock')
    {{--
        daisyUI's own `.dock` distributes children evenly (flex-basis:100%, space-around) and
        shrinks them as more are added -- no native overflow. Overridden here to a horizontally
        scrollable strip instead (fixed-width children, no shrink) so it degrades by scrolling,
        not by squeezing icons unreadably thin, once there are more entries than fit.
        `justify-center-safe`, not plain `center` or `start` -- centers the row when it fits
        (few entries), but "safe" alignment auto-falls-back to start-aligned once it overflows,
        so scrolling can still reach every item instead of clipping content off the left edge
        the way plain `center` + overflow does.
    --}}
    <div class="dock md:hidden overflow-x-auto justify-center-safe [&>*]:shrink-0 [&>*]:basis-auto" id="mobile-nav">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" surface="dock" />
        @endforeach
    </div>
@else
    <ul class="menu p-4">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </ul>
@endif
