@php use Kopling\Core\Ux\Context; @endphp
{{--
    The shared navbar/sidebar/main/rail chrome -- see `Chrome`'s own docblock for why this one
    file now serves Community, Admin, and Style Guide. Only daisyUI semantic classes (bg-base-*,
    text-base-content, border-base-*) are used here, never a raw color -- keeps this open for the
    runtime theme-token system (see Kopling\Core\Ux\Theme).
--}}
<x-k::portal.layout>
    <div class="flex flex-col min-h-screen">
        <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30">
            <div class="flex w-full max-w-7xl mx-auto items-center">
                <div class="flex-1">
                    <span class="text-lg font-semibold px-4">{{ $portal->label }}</span>
                </div>
                {{--
                    `flex` (not just `flex-none`, which only governs how this div behaves as a
                    flex *item* in its own parent above) is what makes `gap-3` actually apply to
                    its children -- without it, `gap-3` is silently a no-op and topbar entries
                    (theme-switcher, the user menu, ...) sit only as far apart as incidental
                    inline whitespace happens to put them.
                --}}
                <div class="flex-none flex items-center gap-3 px-4">
                    <x-k::portal.slot :name="$topbarSlot" />
                </div>
            </div>
        </header>

        <div class="flex flex-1 pb-16 md:pb-0">
            <div class="flex w-full max-w-7xl mx-auto">
                <aside class="w-64 bg-base-100 border-r border-base-300 shrink-0 hidden md:block" id="sidebar">
                    <x-k::portal.slot :name="$sidebarSlot" />
                </aside>

                <main class="flex-1 p-6">
                    <div class="{{ $mainClass }}">
                        {{ $slot }}
                    </div>
                </main>

                @if ($railSlot)
                    <aside class="w-72 border-l border-base-300 p-4 hidden xl:block" id="rail">
                        {{--
                            `subject` is the current route's bound "moment" parameter when there
                            is one (e.g. the discussion page, `/m/{moment}`), null on every other
                            page -- `Context::isRoute()` already exists for exactly this "is this
                            bound to what the current route is about" check, safely returning
                            false on a null subject rather than throwing, so anything registered
                            here can render nothing unless it's actually on a page about one
                            specific Moment. Moment is Core's own model, so this is Core binding
                            its own concept, not reaching into a foreign extension's -- harmless
                            for Admin/Style Guide too, whose own rail slots have nothing
                            registered that would ever read it.
                        --}}
                        <x-k::portal.slot :name="$railSlot"
                            :context="new Context(subject: request()->route('moment'))" />
                    </aside>
                @endif
            </div>
        </div>

        @if ($mobileDock)
            {{--
                Own DOM location, deliberately outside the `hidden md:block` sidebar aside above
                -- `display:none` on an ancestor hides descendants outright regardless of the
                dock's own `position:fixed`/`md:hidden`, so it can't be nested inside it and
                still show below md.
            --}}
            <x-k::community.navigation surface="dock" />
        @endif

        @if ($composerSlot)
            <footer class="border-t border-base-300 p-4" id="composer">
                <x-k::portal.slot :name="$composerSlot" />
            </footer>
        @endif
    </div>
</x-k::portal.layout>
