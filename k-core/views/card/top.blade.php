{{--
    `flex-wrap` (mobile only, `sm:flex-nowrap` restores the original single-row behavior above
    that breakpoint) lets `Title` -- given `basis-full sm:basis-auto` in its own view -- drop onto
    its own line below `sm:` instead of competing with the avatar/author/timestamp/control for
    room on one line, which is what was making the header wrap unpredictably (and drag its own
    generous padding along with whatever it wrapped onto) on narrow screens. `py-3 sm:py-5` trims
    that same padding at the width where it's now a two-line header rather than one.
--}}
@if ($entries->isNotEmpty())
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:flex-nowrap sm:py-5 sm:px-6">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
