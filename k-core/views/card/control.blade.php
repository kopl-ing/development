{{-- No wrapping element of its own -- `$attributes` (e.g. `Top`'s absolute-positioning classes)
     forwards straight onto `Dropdown`'s trigger button, the only element `Dropdown` itself
     merges extra attributes onto (see its own `$attributes->merge()`). --}}
@if ($entries->isNotEmpty())
    <x-k::dropdown :label="__('kopling-core::community.post_actions')" {{ $attributes }}>
        <x-slot:trigger>
            <x-k::icon name="kopling-core::post-actions" class="w-4 h-4" />
        </x-slot:trigger>

        @foreach ($entries as $entry)
            <li><x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" /></li>
        @endforeach
    </x-k::dropdown>
@endif
