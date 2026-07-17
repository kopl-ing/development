@if ($entry)
    <x-dynamic-component :component="$entry->component"
        :data="array_merge($entry->data, ['name' => $name, 'value' => $value, 'placeholder' => $placeholder])"
        :context="$entry->context" />
@endif
