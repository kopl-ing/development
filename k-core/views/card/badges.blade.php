@if ($entries->isNotEmpty())
    <div class="absolute left-4 right-4 top-0 z-10 flex -translate-y-1/2 flex-wrap items-center gap-1.5">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
