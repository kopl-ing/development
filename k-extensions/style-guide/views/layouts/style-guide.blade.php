<x-k::portal.layout>
    <div class="flex min-h-screen">
        <aside class="w-56 border-r border-base-300 shrink-0 p-4 sticky top-0 h-screen overflow-y-auto">
            <ul class="menu">
                <li class="menu-title">{{ $portal->label }}</li>
                <li><a href="#tokens">{{ __('kopling-style-guide::messages.tokens') }}</a></li>
                <li><a href="#forms">{{ __('kopling-style-guide::messages.forms') }}</a></li>
                <li><a href="#actions">{{ __('kopling-style-guide::messages.actions') }}</a></li>
                <li><a href="#editor">{{ __('kopling-style-guide::messages.editor') }}</a></li>
                <li><a href="#card">{{ __('kopling-style-guide::messages.card') }}</a></li>
            </ul>
        </aside>
        <main class="flex-1 p-8 flex flex-col gap-16 max-w-4xl">
            @yield('content')

            {{--
                Renders nothing today (nothing's registered into it) -- included anyway so
                `<x-k::portal.slot>` itself, the primitive any extension targets to add real
                content to a page it doesn't own, is demonstrated rather than only ever used
                indirectly the way Navigation/Sidebar/admin's own sidebar already do.
            --}}
            <x-k::portal.slot name="kopling-style-guide::style-guide.footer" />
        </main>
    </div>
</x-k::portal.layout>
