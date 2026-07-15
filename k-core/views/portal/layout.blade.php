<!DOCTYPE html>
<html lang="en" data-theme="kopling">
<head>
    @include('kopling-core::layouts.partials.head')
</head>
<body class="bg-base-200 text-base-content min-h-screen">
    {{--
        Masks the initial paint (Vite's dev-mode CSS lands a beat after the raw HTML does, same
        cause as the icon-sizing flash) with a full-viewport daisyUI spinner, visible by default
        in plain HTML/CSS before Alpine ever boots. `x-show="!loaded"` only starts hiding it once
        Alpine has initialized and the window `load` event has actually fired -- i.e. every
        stylesheet/script has finished, not just the first paint -- so it never disappears before
        the page underneath is actually ready to look at.
    --}}
    <div x-data="{ loaded: false }" x-init="window.addEventListener('load', () => loaded = true)"
         x-show="!loaded" x-transition:leave="transition ease-out duration-300" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 grid place-items-center bg-base-200">
        <span class="loading loading-infinity loading-lg"></span>
    </div>

    {{ $slot }}
</body>
</html>
