@unless ($hidden)
    @if ($surface === 'dock')
        <a href="{{ route($route) }}">
            @if ($icon)
                <x-k::icon :name="$icon" />
            @endif
            <span class="dock-label">{{ $label }}</span>
        </a>
    @else
        <li>
            <a href="{{ route($route) }}">
                @if ($icon)
                    <x-k::icon :name="$icon" />
                @endif
                {{ $label }}
            </a>
        </li>
    @endif
@endunless
