@php
    use Kopling\Core\Extension\Manager;
    use Kopling\Core\Settings\Settings;
    use Kopling\Core\Ux\Theme;
@endphp
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if ($description = Settings::get('kopling-core::community-description'))
    <meta name="description" content="{{ $description }}">
@endif
<title>@yield('title', Settings::get('kopling-core::community-name', 'Kopling'))</title>
{{-- `Manager::viteOrDist()` -- @vite() inside this monorepo (dev server, or npm run build
     already produced a manifest entry), the committed k-core/dist/{name}.* otherwise (a
     standalone `kopling/core` install has no monorepo Vite manifest at all). `app` is core's
     own always-loaded bundle; the rest are tiny always-loaded shims whose real payload
     (TipTap, emoji-mart, tagify) lazy-loads via dynamic import() once its own mount point/
     trigger actually exists. --}}
@foreach (Manager::CORE_COMPILED_BUNDLES as $coreBundle)
    {!! Manager::viteOrDist(app(Manager::class)->path('kopling/core'), $coreBundle) !!}
@endforeach
<style>{!! Theme::css() !!}</style>
{{-- Every extension's css/js attached to the Portal this request resolved to -- hand-written
     (css/js) and compiled (compiledAssets(), via Manager::viteOrDist()) are two independent
     mechanisms, see PortalExtension's own docblock. --}}
@if ($portal ?? null)
    @foreach (app(Manager::class)->portalExtensions()->get($portal->id, collect()) as $extension)
        @if ($extension->css)
            <link rel="stylesheet" href="{{ Manager::assetUrl($extension->css) }}">
        @endif
        @if ($extension->js)
            <script type="module" src="{{ Manager::assetUrl($extension->js) }}"></script>
        @endif
        @if ($extension->compiledAssetsRoot)
            {!! Manager::viteOrDist($extension->compiledAssetsRoot, $extension->compiledAssetsName) !!}
        @endif
    @endforeach
@endif
