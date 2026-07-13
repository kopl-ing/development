@if ($surface === 'dock')
    <a href="{{ route($route) }}">
        @if ($icon)
            {!! $icon !!}
        @endif
        <span class="dock-label">{{ $label }}</span>
    </a>
@else
    <li>
        <a href="{{ route($route) }}">
            @if ($icon)
                {!! $icon !!}
            @endif
            {{ $label }}
        </a>
    </li>
@endif
