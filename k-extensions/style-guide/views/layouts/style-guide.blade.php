{{--
    Mirrors the same navbar+sidebar+main shape `kopling-admin::layouts.admin` already
    establishes -- its own file, not a shared partial (Admin's layout isn't designed for
    cross-extension reuse either), but following the same structure rather than inventing a
    third, differently-shaped chrome.
--}}
<x-k::portal.layout>
    <div class="flex min-h-screen">
        <aside class="w-56 bg-base-100 border-r border-base-300 shrink-0 p-4 sticky top-0 h-screen overflow-y-auto">
            <ul class="menu w-full">
                <li class="menu-title">{{ $portal->label }}</li>
                <li><a href="#tokens">{{ __('kopling-style-guide::messages.tokens') }}</a></li>
                <li><a href="#forms">{{ __('kopling-style-guide::messages.forms') }}</a></li>
                <li><a href="#actions">{{ __('kopling-style-guide::messages.actions') }}</a></li>
                <li><a href="#editor">{{ __('kopling-style-guide::messages.editor') }}</a></li>
                <li><a href="#card">{{ __('kopling-style-guide::messages.card') }}</a></li>
            </ul>
        </aside>

        <div class="flex-1 flex flex-col">
            <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-10">
                <div class="flex-1">
                    <span class="text-lg font-semibold px-4">{{ $portal->label }}</span>
                </div>
                <div class="flex-none gap-2 px-4">
                    {{--
                        Renders nothing today (nothing's registered into it) -- included anyway
                        so `<x-k::portal.slot>` itself, the primitive any extension targets to add
                        real content to a page it doesn't own, is demonstrated rather than only
                        ever used indirectly the way Navigation/Sidebar/admin's own sidebar do.
                    --}}
                    <x-k::portal.slot name="kopling-style-guide::style-guide.topbar" />
                </div>
            </header>

            <main class="flex-1 p-8 flex flex-col gap-16 max-w-4xl">
                @yield('content')
            </main>
        </div>
    </div>
</x-k::portal.layout>
