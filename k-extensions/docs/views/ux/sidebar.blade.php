@if ($tree->isEmpty())
    <p class="text-sm opacity-60 px-2">{{ __('kopling-docs::messages.no_pages_synced') }}</p>
@else
    {{-- No target: boosted-without-target swaps `document.body` (see CLAUDE.md gotchas). --}}
    <ul class="menu" hx-boost:inherited="true">
        @foreach ($tree as $section => $pages)
            <li class="menu-title">{{ $section }}</li>
            @foreach ($pages as $page)
                <li><a href="{{ route('kopling-docs::docs/show', $page->slug) }}">{{ $page->title }}</a></li>
            @endforeach
        @endforeach
    </ul>
@endif
