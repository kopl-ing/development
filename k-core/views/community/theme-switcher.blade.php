{{--
    Theme toggle for the community topbar. One click cycles to the next installed theme (with
    two themes, a straight toggle) — no dropdown. Each click is a plain POST to theme.set that
    sets the cookie and re-renders the whole page under the new theme, so it needs no
    JavaScript. Only shows when there's more than one theme to switch between.
--}}
@php
    $ids = array_keys($themes);
    $index = array_search($active, $ids, true);
    $next = $ids[($index === false ? 0 : $index + 1) % count($ids)];
@endphp

@if (count($themes) > 1)
    <form method="POST" action="{{ route('kopling-core::community/theme.set') }}" class="inline-block">
        @csrf
        <input type="hidden" name="theme" value="{{ $next }}">
        <button type="submit" class="btn btn-ghost btn-sm btn-square"
                title="{{ __('kopling-core::theme.switch') }} — {{ $themes[$next] }}"
                aria-label="{{ __('kopling-core::theme.switch') }} — {{ $themes[$next] }}">
            <x-k::icon name="kopling-core::theme-switch" width="18" height="18" />
        </button>
    </form>
@endif
