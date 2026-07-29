@if ($surface === 'dock')
    <a href="{{ $url }}">
        @if ($icon)
            <x-k::icon :name="$icon" />
        @endif
        <span class="dock-label">{{ $label }}</span>
    </a>
@else
    <li>
        <a href="{{ $url }}">
            @if ($icon)
                <x-k::icon :name="$icon" />
            @endif
            {{ $label }}
        </a>
    </li>
@endif
