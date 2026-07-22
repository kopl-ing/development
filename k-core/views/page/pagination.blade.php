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
@endphp
@if ($paginator->hasPages())
    <nav {{ $attributes->merge(['class' => 'flex justify-center']) }} aria-label="{{ __('kopling-core::ux.pagination_navigation') }}">
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
