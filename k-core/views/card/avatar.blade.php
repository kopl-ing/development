{{-- Per-person deterministic hue (`Person::avatarColor()`), white text -- same as reactions'
     own chip avatars, and always readable regardless of theme. Falls back to a plain themed
     surface when there's no resolvable Person. --}}
<div class="avatar avatar-placeholder not-italic" @if ($name) aria-label="{{ $name }}" @endif>
    <div class="w-8 rounded-full sm:w-10 {{ $color ? 'text-white' : 'bg-base-300 text-base-content' }}"
         @if ($color) style="background:{{ $color }}" @endif>
        <span>{{ $initials }}</span>
    </div>
</div>
