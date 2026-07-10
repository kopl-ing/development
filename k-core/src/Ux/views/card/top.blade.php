<div class="flex items-center gap-3">
    @foreach ($entries as $entry)
        <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
    @endforeach
</div>
