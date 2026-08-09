@foreach ($entries as $entry)
    <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" :checked="$loop->first" />
@endforeach
