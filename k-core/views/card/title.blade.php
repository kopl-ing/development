{{-- `min-w-0` on both `.card-title` (a flex row) and its child text: flex items default to
     `min-width: auto` (their own content size), not 0, so a long `white-space:nowrap` title
     (from `truncate`) would refuse to shrink and force this row -- and the card -- wider than
     the viewport on mobile instead of actually truncating. --}}
<h2 class="card-title overflow-x-auto">
    @if ($url)
        <a href="{{ $url }}" class="transition-colors group-hover:text-primary">{{ $title }}</a>
    @else
        <span>{{ $title }}</span>
    @endif
</h2>
