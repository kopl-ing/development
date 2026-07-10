<li>
    <a href="{{ route($route) }}">
        @if ($icon)
            <span class="{{ $icon }}"></span>
        @endif
        {{ $label }}
    </a>
</li>
