<h2 class="card-title">
    @if ($url)
        <a href="{{ $url }}" class="link link-hover">{{ $title }}</a>
    @else
        {{ $title }}
    @endif
</h2>
<p>{{ $body }}</p>
