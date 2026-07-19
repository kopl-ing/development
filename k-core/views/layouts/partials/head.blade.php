<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Kopling')</title>
@vite(['k-core/src/Ux/css/app.css', 'k-core/src/Ux/js/app.js'])
{{--
    editor.js is its own Vite entry, not folded into app.js -- a page with no editor mount point
    still loads this tiny shim, but the real TipTap/ProseMirror payload (editor-tiptap.js) is
    only pulled in via dynamic import() once a mount point actually exists (see Ux/js/editor.js).
    Every page in the Community portal today can render the composer, so this loads
    unconditionally rather than being gated per-Portal the way extension assets are.
--}}
@vite(['k-core/src/Ux/css/editor.css', 'k-core/src/Ux/js/editor.js'])
{{--
    Same shape as editor.js above: a tiny always-loaded shim, with the real emoji-mart payload
    only ever dynamically import()ed the first time a `<x-k::form.emoji-picker>` trigger is
    actually clicked (see Ux/js/emoji-picker.js) -- unlike the editor there's no eager mount at
    all, so a page carrying the markup but never opened costs nothing beyond this shim. Loaded
    unconditionally here (not gated per-Portal) since it's a Core primitive any Portal/extension
    can use, not owned by whichever one happens to render first.
--}}
@vite(['k-core/src/Ux/css/emoji-picker.css', 'k-core/src/Ux/js/emoji-picker.js'])
{{--
    Same shape as editor.js again: a tiny always-loaded shim, with the real `@yaireo/tagify`
    payload only ever dynamically import()ed once a `<x-k::form.tag-input>` mount point actually
    exists (see Ux/js/tag-input.js) -- mounts eagerly, like the editor, not behind a click like
    the emoji picker, since a tag input needs to be visible and interactive the moment its page
    loads. Loaded unconditionally here for the same "Core primitive, not Portal-owned" reason
    emoji-picker.js already is.
--}}
@vite(['k-core/src/Ux/css/tag-input.css', 'k-core/src/Ux/js/tag-input.js'])
<style>{!! \Kopling\Core\Ux\Theme::css() !!}</style>
{{--
    Every extension's css/js attached to the Portal this request resolved to (see
    Http\Middleware\InjectPortal, which shares `$portal` as a view global) -- null on a request
    that isn't under any Portal's route group, in which case there's nothing to link.
--}}
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
