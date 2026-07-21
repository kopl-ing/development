@php use Kopling\Core\Ux\Context; @endphp
<x-k::portal.layout>
    <div class="flex flex-col min-h-screen">
        <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30">
            <div class="flex w-full max-w-7xl mx-auto items-center">
                <div class="flex-1">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $label }}" class="h-8 px-4">
                    @else
                        <span class="text-lg font-semibold px-4">{{ $label }}</span>
                    @endif
                </div>
                {{-- `flex` (not just `flex-none`) is what makes `gap-3` apply to its children. --}}
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

                <main class="flex-1 p-4 sm:p-6">
                    <div class="{{ $mainClass }}">
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

        @if ($mobileDock)
            {{-- Outside the `hidden md:block` sidebar on purpose -- `display:none` on an
                 ancestor would hide this regardless of its own `md:hidden`. --}}
            <x-k::community.navigation surface="dock" />
        @endif

        @if ($composerSlot)
            <footer class="border-t border-base-300 p-4" id="composer">
                <x-k::portal.slot :name="$composerSlot" />
            </footer>
        @endif
    </div>
</x-k::portal.layout>
