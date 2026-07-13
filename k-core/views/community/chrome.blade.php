{{--
    The Community portal's chrome only -- topbar/sidebar/rail/composer -- with no opinion on
    what the main content actually is. `layouts/community.blade.php` (the feed) fills this with
    the tabs/poller/moment loop; anything else that wants to sit inside the same site
    experience (discussions' show page, a future tags page) fills it with its own content
    instead. See `Chrome`'s own docblock for why it resolves the Community portal itself rather
    than requiring one from the caller. Only daisyUI semantic classes (bg-base-*, text-base-
    content, border-base-*) are used here, never a raw color -- keeps this open for the runtime
    theme-token system (see Kopling\Core\Ux\Theme).
--}}
<x-k::portal.layout>
    <div class="flex flex-col min-h-screen">
        <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30">
            <div class="flex w-full max-w-7xl mx-auto items-center">
                <div class="flex-1">
                    <span class="text-lg font-semibold px-4">{{ $portal->label }}</span>
                </div>
                <div class="flex-none gap-2 px-4">
                    <x-k::portal.slot name="kopling-core::community.topbar" />
                </div>
            </div>
        </header>

        <div class="flex flex-1">
            <div class="flex w-full max-w-7xl mx-auto">
                <aside class="w-64 bg-base-100 border-r border-base-300 shrink-0" id="sidebar">
                    <x-k::community.sidebar />
                </aside>

                <main class="flex-1 p-6">
                    <div class="max-w-2xl mx-auto">
                        {{ $slot }}
                    </div>
                </main>

                <aside class="w-72 border-l border-base-300 p-4 hidden xl:block" id="rail">
                    <x-k::portal.slot name="kopling-core::community.rail" />
                </aside>
            </div>
        </div>

        <footer class="border-t border-base-300 p-4" id="composer">
            <x-k::portal.slot name="kopling-core::community.composer" />
        </footer>
    </div>
</x-k::portal.layout>
