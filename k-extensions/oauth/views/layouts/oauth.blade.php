<x-k::community.chrome
    portal-id="kopling-oauth::oauth"
    topbar-slot="kopling-oauth::oauth.topbar"
    :sidebar-slot="null"
    :rail-slot="null"
    :composer-slot="null"
    :mobile-dock="false"
    main-class="max-w-lg mx-auto"
>
    @yield('content')
</x-k::community.chrome>
