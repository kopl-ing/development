<aside class="w-64 bg-base-100 border-r border-base-300 shrink-0">
    <ul class="menu p-4">
        <li class="menu-title">{{ $portal->label }}</li>
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" />
        @endforeach
    </ul>
</aside>
