<div class="p-4">
    @foreach ($entries as $entry)
        <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
    @endforeach
</div>
