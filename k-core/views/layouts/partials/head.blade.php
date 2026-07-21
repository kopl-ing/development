@php use Kopling\Core\Settings\Settings; @endphp
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if ($description = Settings::get('kopling-core::community-description'))
    <meta name="description" content="{{ $description }}">
@endif
<title>@yield('title', Settings::get('kopling-core::community-name', 'Kopling'))</title>
@vite(['k-core/src/Ux/css/app.css', 'k-core/src/Ux/js/app.js'])
{{-- Each of these is a tiny always-loaded shim; the real payload (TipTap, emoji-mart, tagify)
     lazy-loads via dynamic import() once its own mount point/trigger actually exists. --}}
@vite(['k-core/src/Ux/css/editor.css', 'k-core/src/Ux/js/editor.js'])
@vite(['k-core/src/Ux/css/emoji-picker.css', 'k-core/src/Ux/js/emoji-picker.js'])
@vite(['k-core/src/Ux/css/tag-input.css', 'k-core/src/Ux/js/tag-input.js'])
@vite(['k-core/src/Ux/css/icon-picker.css', 'k-core/src/Ux/js/icon-picker.js'])
<style>{!! \Kopling\Core\Ux\Theme::css() !!}</style>
{{-- Every extension's css/js attached to the Portal this request resolved to. --}}
@if ($portal ?? null)
    @foreach (app(\Kopling\Core\Extension\Manager::class)->portalExtensions()->get($portal->id, collect()) as $extension)
        @if ($extension->css)
            <link rel="stylesheet" href="{{ \Kopling\Core\Extension\Manager::assetUrl($extension->css) }}">
        @endif
        @if ($extension->js)
            <script type="module" src="{{ \Kopling\Core\Extension\Manager::assetUrl($extension->js) }}"></script>
        @endif
    @endforeach
@endif
