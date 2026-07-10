{{--
    A distinct slot map from the Admin portal on purpose -- this is the point of the whole
    exercise: each Portal's layout defines its own named regions, not one shared shape every
    Portal is forced into. Structure only for now (see CLAUDE.md/decisions.md): every region
    below is a real, resolvable slot, but only "core::side-navigation"-style content exists
    anywhere yet -- sidebar/rail/content-top/composer render empty until something real
    registers into them. Only daisyUI semantic classes (bg-base-*, text-base-content, border-
    base-*) are used here, never a raw color -- keeps this open for the runtime theme-token
    system planned in admin/theme.blade.php.
--}}
<x-k::portal.layout>
    <div class="flex flex-col min-h-screen">
        <header class="navbar bg-base-100 border-b border-base-300">
            <div class="flex-1">
                <span class="text-lg font-semibold px-4">{{ $portal->label }}</span>
            </div>
            <div class="flex-none gap-2 px-4">
                <x-k::portal.slot name="core::community.topbar" />
            </div>
        </header>

        <div class="flex flex-1">
            <aside class="w-64 bg-base-100 border-r border-base-300 shrink-0">
                <ul class="menu p-4">
                    <x-k::portal.slot name="core::community.sidebar" />
                </ul>
            </aside>

            <main class="flex-1 p-6">
                <div class="mb-4">
                    <x-k::portal.slot name="core::community.content-top" />
                </div>

                @yield('content')
            </main>

            <aside class="w-72 border-l border-base-300 p-4 hidden xl:block">
                <x-k::portal.slot name="core::community.rail" />
            </aside>
        </div>

        <footer class="border-t border-base-300 p-4">
            <x-k::portal.slot name="core::community.composer" />
        </footer>
    </div>
</x-k::portal.layout>
