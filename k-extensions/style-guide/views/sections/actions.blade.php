<section id="actions" class="flex flex-col gap-4">
    <h2 class="text-xl font-semibold border-b border-base-300 pb-2">
        {{ __('kopling-style-guide::messages.actions') }}
    </h2>

    <div class="flex flex-wrap items-center gap-6">
        <div class="flex flex-col items-center gap-2">
            <x-k::link :data="['label' => 'Link', 'route' => 'kopling-style-guide::style-guide/index']" />
            <span class="text-xs text-base-content/70">Link</span>
        </div>

        <div class="flex flex-col items-center gap-2">
            <x-k::dropdown label="Example dropdown">
                <x-slot:trigger>Dropdown</x-slot:trigger>
                <li><a>First option</a></li>
                <li><a>Second option</a></li>
            </x-k::dropdown>
            <span class="text-xs text-base-content/70">Dropdown</span>
        </div>

        <div class="flex flex-col items-center gap-2">
            <x-k::modal label="Example modal">
                <x-slot:trigger>Modal</x-slot:trigger>
                <p>Modal body content goes here.</p>
            </x-k::modal>
            <span class="text-xs text-base-content/70">Modal</span>
        </div>

        <div class="flex flex-col items-center gap-4">
            <div class="flex items-center gap-3">
                <x-k::icon name="kopling-core::home" />
                <x-k::icon name="kopling-core::theme-switch" />
                <x-k::icon name="kopling-core::post-actions" />
            </div>
            <span class="text-xs text-base-content/70">Icon</span>
        </div>
    </div>
</section>
