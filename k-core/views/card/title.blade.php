<h2 class="card-title flex-1 min-w-0">
    @if ($url)
        <a href="{{ $url }}" class="link link-hover truncate">{{ $title }}</a>
    @else
        <span class="truncate">{{ $title }}</span>
    @endif
</h2>
