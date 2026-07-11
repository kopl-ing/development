<div class="mt-2">
    @foreach ($entries as $entry)
        <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
    @endforeach
</div>
