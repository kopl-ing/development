@php use Kopling\Core\Ux\Context; @endphp
<x-k::portal.layout>
    <div class="flex flex-col min-h-screen">
        <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30">
            <div class="flex w-full max-w-7xl mx-auto items-center">
                <div class="flex-1 flex items-center min-w-0">
                    @if ($sidebarSlot)
                        <label for="sidebar-drawer" class="btn btn-ghost btn-square ml-2 md:hidden" aria-label="{{ __('kopling-core::community.menu') }}">
                            <x-k::icon name="kopling-core::menu" />
                        </label>
                    @endif
                    @if ($showLabel && $logo)
                        <img src="{{ $logo }}" alt="{{ $label }}" class="h-8 px-4">
                    @elseif ($showLabel)
                        <span class="text-lg font-semibold px-4">{{ $label }}</span>
                    @endif
                    @if ($topbarStartSlot)
                        <div class="flex-1 flex items-center gap-3 px-4 min-w-0">
                            <x-k::portal.slot :name="$topbarStartSlot" />
                        </div>
                    @endif
                </div>
                {{-- `flex` (not just `flex-none`) is what makes `gap-3` apply to its children. --}}
                <div class="flex-none flex items-center gap-3 px-4">
                    <x-k::portal.slot :name="$topbarSlot" />
                </div>
            </div>
        </header>

        <div @class(['flex flex-1', 'pb-16 md:pb-0' => $mobileDock])>
            <div @class(['w-full max-w-7xl mx-auto', 'drawer md:drawer-open' => $sidebarSlot, 'flex' => ! $sidebarSlot])>
                @if ($sidebarSlot)
                    <input id="sidebar-drawer" type="checkbox" class="drawer-toggle">
                    {{-- `md:top-16`/`md:h-*` match the sticky navbar's `min-h-16`; change both together. --}}
                    <div class="drawer-side z-40 md:z-20 md:top-16 md:h-[calc(100dvh-4rem)]">
                        <label for="sidebar-drawer" class="drawer-overlay" aria-label="{{ __('kopling-core::ux.close') }}"></label>
                        <aside class="w-64 min-h-full bg-base-100 border-r border-base-300" id="sidebar">
                            <x-k::portal.slot :name="$sidebarSlot" />
                        </aside>
                    </div>
                @endif

                <div @class(['flex flex-1 min-w-0', 'drawer-content' => $sidebarSlot])>
                    <main class="flex-1 p-4 sm:p-6">
                        {{-- Shared swap target for `Card\Title`'s `hx-boost`ed link (`card/title.blade.php`). --}}
                        <div id="main-content" class="{{ $mainClass }}">
                            {{ $slot }}
                        </div>
                    </main>

                    @if ($railSlot)
                        <aside class="w-72 border-l border-base-300 p-4 hidden xl:block" id="rail">
                            {{-- The current route's bound "moment" param, null on every other page. --}}
                            <x-k::portal.slot :name="$railSlot"
                                :context="new Context(subject: request()->route('moment'))" />
                        </aside>
                    @endif
                </div>
            </div>
        </div>

        @if ($mobileDock)
            {{-- Outside the drawer sidebar on purpose -- its hidden state on mobile would hide this too. --}}
            <x-k::community.navigation surface="dock" />
        @endif

        @if ($composerSlot)
            <footer class="border-t border-base-300 p-4" id="composer">
                <x-k::portal.slot :name="$composerSlot" />
            </footer>
        @endif
    </div>
</x-k::portal.layout>
