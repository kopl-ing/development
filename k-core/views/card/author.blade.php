@if($url)
    <a href="{{ $url }}" class="font-semibold transition-colors hover:text-primary">{{ $name }}</a>
@else
    <p class="font-semibold">{{ $name }}</p>
@endif
