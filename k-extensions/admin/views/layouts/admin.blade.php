{{--
    Reuses Core's own shared chrome (`Community\Chrome`) instead of hand-rolling its own
    topbar/sidebar/rail markup -- Community/Admin/Style Guide had quietly drifted into three
    different widths despite starting as copies of each other (see decisions.md). `main-class=""`
    (no extra narrowing, unlike Community's own reading-column default) since Admin's own content
    -- settings tables, people/group lists -- wants the full width up to Chrome's own outer
    `max-w-7xl`, not a narrow centered column.
--}}
<x-k::community.chrome
    portal-id="kopling-admin::admin"
    topbar-slot="kopling-admin::admin.topbar"
    sidebar-slot="kopling-admin::admin.sidebar-panel"
    rail-slot="kopling-admin::admin.rail"
    :composer-slot="null"
    :mobile-dock="false"
    main-class=""
>
    @yield('content')
</x-k::community.chrome>
