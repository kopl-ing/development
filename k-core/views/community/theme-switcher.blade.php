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
    <form method="POST" action="{{ route('kopling-core::community/theme.set') }}">
        @csrf
        <input type="hidden" name="theme" value="{{ $next }}">
        <button type="submit" class="btn btn-ghost btn-sm btn-square"
                title="{{ __('kopling-core::theme.switch') }} — {{ $themes[$next] }}"
                aria-label="{{ __('kopling-core::theme.switch') }} — {{ $themes[$next] }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125 0-.938.75-1.688 1.688-1.688H16c3.313 0 6-2.688 6-6C22 6.037 17.5 2 12 2z"/>
            </svg>
        </button>
    </form>
@endif
