{{--
    Reuses Core's own shared chrome, same as kopling-admin::layouts.admin -- no navigation/rail
    entries registered into this portal's own sidebar/rail slots yet in Phase 1 (nothing to put
    there besides the queue itself), so the sidebar simply renders empty for now.
--}}
<x-k::community.chrome
    portal-id="kopling-moderation::moderation"
    topbar-slot="kopling-moderation::moderation.topbar"
    sidebar-slot="kopling-moderation::moderation.sidebar-panel"
    :rail-slot="null"
    :composer-slot="null"
    :mobile-dock="false"
    main-class=""
>
    @yield('content')
</x-k::community.chrome>
