<x-k::portal.layout>
    <div class="flex min-h-screen">
        <aside class="w-64 bg-base-100 border-r border-base-300 shrink-0">
            <ul class="menu p-4">
                <li class="menu-title">{{ $portal->label }}</li>
                <x-k::portal.slot name="kopling-core::side-navigation" />
            </ul>
        </aside>
        <div class="flex-1">
            <header class="navbar bg-base-100 border-b border-base-300">
                <span class="text-lg font-semibold px-4">{{ $portal->label }}</span>
            </header>
            <main class="p-6">
                @yield('content')
            </main>
        </div>
    </div>
</x-k::portal.layout>
