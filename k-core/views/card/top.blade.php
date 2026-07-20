@if ($entries->isNotEmpty())
    <div class="flex items-center gap-3 px-4 py-5 sm:px-6">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
