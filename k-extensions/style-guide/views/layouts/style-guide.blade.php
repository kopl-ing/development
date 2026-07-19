{{--
    Reuses Core's own shared chrome (`Community\Chrome`) instead of hand-rolling its own
    topbar/sidebar/rail markup -- Community/Admin/Style Guide had quietly drifted into three
    different widths despite starting as copies of each other (see decisions.md). `main-class=""`
    (no extra narrowing) since this page's own component showcase wants the full width up to
    Chrome's own outer `max-w-7xl`, not a narrow centered column.
--}}
<x-k::community.chrome
    portal-id="kopling-style-guide::style-guide"
    topbar-slot="kopling-style-guide::style-guide.topbar"
    sidebar-slot="kopling-style-guide::style-guide.sidebar-panel"
    rail-slot="kopling-style-guide::style-guide.rail"
    :composer-slot="null"
    :mobile-dock="false"
    main-class=""
>
    <div class="flex flex-col gap-16">
        @yield('content')
    </div>
</x-k::community.chrome>
