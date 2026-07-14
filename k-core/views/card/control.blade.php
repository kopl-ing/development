@if ($entries->isNotEmpty())
    <x-k::dropdown :label="__('kopling-core::community.post_actions')" class="ml-auto">
        <x-slot:trigger>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true">
                <circle cx="12" cy="5" r="1.5" />
                <circle cx="12" cy="12" r="1.5" />
                <circle cx="12" cy="19" r="1.5" />
            </svg>
        </x-slot:trigger>

        @foreach ($entries as $entry)
            <li><x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" /></li>
        @endforeach
    </x-k::dropdown>
@endif
