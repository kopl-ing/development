{{-- `min-w-0` on both `.card-title` (a flex row) and its child text: flex items default to
     `min-width: auto` (their own content size), not 0, so a long `white-space:nowrap` title
     (from `truncate`) would refuse to shrink and force this row -- and the card -- wider than
     the viewport on mobile instead of actually truncating. --}}
<h2 class="card-title overflow-x-auto">
    @if ($url)
        {{-- `data-card-primary-link` is app.js's whole-card click hook; `hx-target`/`hx-select` reuse `page/pagination.blade.php`'s `#main-content` swap idiom. `$boost` (`Context::$boost`) opts a Card embedded outside Kopling's own chrome -- no `#main-content` there to swap into -- back to a plain full navigation. --}}
        <a href="{{ $url }}" data-card-primary-link class="transition-colors group-hover:text-primary"
           @if ($boost) hx-boost="true" hx-target="#main-content" hx-select="#main-content" hx-swap="outerHTML show:top" hx-push-url="true" @endif
        >{{ $title }}</a>
    @else
        <span>{{ $title }}</span>
    @endif
</h2>
