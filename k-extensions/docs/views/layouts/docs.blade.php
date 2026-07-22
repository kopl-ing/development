{{--
    Reuses Core's own shared chrome (`Community\Chrome`) -- same non-community, non-composer
    surface shape `kopling-style-guide::layouts.style-guide` already proves it handles, rather
    than hand-rolling a second topbar/sidebar implementation.
--}}
<x-k::community.chrome
    portal-id="kopling-docs::docs"
    topbar-slot="kopling-docs::docs.topbar"
    sidebar-slot="kopling-docs::docs.sidebar-panel"
    rail-slot="kopling-docs::docs.rail"
    :composer-slot="null"
    :mobile-dock="false"
>
    @yield('content')
</x-k::community.chrome>
