@php use Kopling\Pages\Page; @endphp
{{--
    Its own thin topbar+footer, not Community\Chrome -- chrome is in-app shell (sidebar/rail/
    composer); a public marketing/static-page surface isn't. Nav is DB-content-driven (published
    + show_in_nav pages), not Ux-slot-driven -- a different data source than extension-declared
    Ux::add() entries, computed straight here rather than threaded through every controller
    action that renders this layout.
--}}
<x-k::portal.layout>
    <div class="flex flex-col min-h-screen">
        <header class="navbar bg-base-100 border-b border-base-300 px-4">
            <div class="flex-1">
                <a href="{{ route('kopling-pages::pages/index') }}" class="text-lg font-bold">{{ config('app.name') }}</a>
            </div>
            <nav class="flex-none flex items-center gap-4">
                @foreach (Page::where('published', true)->where('show_in_nav', true)->orderBy('nav_order')->get() as $navPage)
                    <a href="{{ route('kopling-pages::pages/show', $navPage->path) }}" class="link link-hover">{{ $navPage->title }}</a>
                @endforeach
                <x-k::portal.slot name="kopling-pages::pages.topbar" />
            </nav>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="footer footer-center p-6 text-base-content/60 border-t border-base-300">
            <p>&copy; {{ now()->year }}</p>
        </footer>
    </div>
</x-k::portal.layout>
