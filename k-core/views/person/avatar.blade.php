{{-- Per-person deterministic hue (`Person::avatarColor()`), white text -- same as reactions'
     own chip avatars, and always readable regardless of theme. Falls back to a plain themed
     surface when there's no resolvable color. `$mask` is a daisyUI mask utility class
     (`mask-squircle` by default) or null for a plain circle via `rounded-full`. Extra attributes
     (e.g. `class="text-xs"` from `AvatarGroup`'s smaller avatars) land on the inner sized div,
     not the outer `.avatar` wrapper -- that's the element `$size`/the mask already target. --}}
<div class="avatar avatar-placeholder not-italic {{ $presence ? "avatar-{$presence}" : '' }}"
     @if ($name) title="{{ $name }}" aria-label="{{ $name }}" @endif>
    <div {{ $attributes->merge(['class' => trim($size.' '.($mask ? "mask {$mask}" : 'rounded-full').' '.($color ? 'text-white' : 'bg-base-300 text-base-content'))]) }}
         @if ($color) style="background:{{ $color }}" @endif>
        <span>{{ $initials }}</span>
    </div>
</div>
