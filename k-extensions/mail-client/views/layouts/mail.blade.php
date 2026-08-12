{{--
    Reuses Core's own shared chrome (`Community\Chrome`), same as Admin/Docs -- no hand-rolled
    topbar/sidebar/rail markup. `sidebar-panel` carries the folder tree, `main` (@yield('content'))
    the message-list/reading-pane split; `rail`/`composer` unused for now.
--}}
<x-k::community.chrome
    portal-id="kopling-mail-client::mail"
    topbar-slot="kopling-mail-client::mail.topbar"
    sidebar-slot="kopling-mail-client::mail.sidebar-panel"
    :rail-slot="null"
    :composer-slot="null"
    :mobile-dock="false"
    main-class=""
>
    @yield('content')
</x-k::community.chrome>
