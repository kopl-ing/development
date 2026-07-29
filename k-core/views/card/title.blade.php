<h2 class="card-title">
    @if ($url)
        <a href="{{ $url }}" class="truncate transition-colors group-hover:text-primary">{{ $title }}</a>
    @else
        <span class="truncate">{{ $title }}</span>
    @endif
</h2>
