{{--
    Reuses Core's own shared chrome, same as kopling-admin::layouts.admin -- QueueNav (this
    portal's own kopling-moderation::moderation.sidebar-panel entry) is the status filter nav.
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
