{{-- `flex-wrap` (mobile only) lets `Title`'s own `basis-full` drop it onto its own line below
     `sm:`, instead of competing with avatar/author/timestamp/control for room on one. --}}
@if ($entries->isNotEmpty())
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:flex-nowrap sm:py-5 sm:px-6">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
