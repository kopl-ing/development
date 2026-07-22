{{--
    `$paginator->linkCollection()` (not the protected `elements()`/default `render()` view) --
    it's the one public API that already gives an elided page-number window (with '...' entries)
    without pulling in Laravel's own bundled Tailwind pagination view. Sliced to drop its own
    prepended/pushed Previous/Next entries: those fall back to the `pagination.previous`/`.next`
    translation keys, which this app never publishes, so Previous/Next render from this
    component's own `kopling-core::ux` strings instead.
--}}
@php
    $pages = $paginator->linkCollection()->slice(1, -1);

    // htmx 4 inheritance is explicit (`:inherited`) -- without it these would sit inertly on
    // `<nav>` itself, never reaching the plain `<a>` tags below that actually trigger a request.
    // `show:top` scrolls `$target` back into view after the swap -- otherwise the page would
    // stay at whatever scroll position clicking "Next" happened to leave it at, mid-old-content.
    $htmxAttributes = $target ? [
        'hx-boost:inherited' => 'true',
        'hx-target:inherited' => $target,
        'hx-select:inherited' => $target,
        'hx-swap:inherited' => 'outerHTML show:top',
        'hx-push-url:inherited' => 'true',
    ] : [];
@endphp
@if ($paginator->hasPages())
    <nav
        {{ $attributes->merge(array_merge(['class' => 'flex justify-center'], $htmxAttributes)) }}
        aria-label="{{ __('kopling-core::ux.pagination_navigation') }}"
    >
        <div class="join">
            @if ($paginator->onFirstPage())
                <span class="join-item btn btn-disabled" tabindex="-1" role="button" aria-disabled="true" aria-label="{{ __('kopling-core::ux.previous') }}">
                    <x-k::icon name="kopling-core::pagination-previous" class="h-3 w-3" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="join-item btn" aria-label="{{ __('kopling-core::ux.previous') }}">
                    <x-k::icon name="kopling-core::pagination-previous" class="h-3 w-3" />
                </a>
            @endif

            @foreach ($pages as $page)
                @if ($page['active'])
                    <span class="join-item btn btn-active btn-disabled" tabindex="-1" role="button" aria-disabled="true" aria-current="page">{{ $page['label'] }}</span>
                @elseif ($page['url'] === null)
                    <span class="join-item btn btn-disabled" tabindex="-1" role="button" aria-disabled="true">{{ $page['label'] }}</span>
                @else
                    <a href="{{ $page['url'] }}" class="join-item btn">{{ $page['label'] }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="join-item btn" aria-label="{{ __('kopling-core::ux.next') }}">
                    <x-k::icon name="kopling-core::pagination-next" class="h-3 w-3" />
                </a>
            @else
                <span class="join-item btn btn-disabled" tabindex="-1" role="button" aria-disabled="true" aria-label="{{ __('kopling-core::ux.next') }}">
                    <x-k::icon name="kopling-core::pagination-next" class="h-3 w-3" />
                </span>
            @endif
        </div>
    </nav>
@endif
