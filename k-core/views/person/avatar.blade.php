{{-- Per-person deterministic hue (`Person::avatarColor()`), white text -- same as reactions'
     own chip avatars, and always readable regardless of theme. Falls back to a plain themed
     surface when there's no resolvable color. `$mask` is a daisyUI mask utility class
     (`mask-squircle` by default) or null for a plain circle via `rounded-full`. Extra attributes
     land on the inner sized div, not the outer `.avatar` wrapper -- that's the element `$size`/
     the mask already target. `@container` on that same div plus `cqw` on the initials scales
     the text with whatever `$size` resolves to, instead of every caller having to hand-pick a
     matching text size alongside it. --}}
<div class="avatar avatar-placeholder not-italic {{ $presence ? "avatar-{$presence}" : '' }}"
     @if ($name) title="{{ $name }}" aria-label="{{ $name }}" @endif>
    <div {{ $attributes->merge(['class' => trim($size.' @container '.($mask ? "mask {$mask}" : 'rounded-full').' '.($color ? 'text-white' : 'bg-base-300 text-base-content'))]) }}
         @if ($color) style="background:{{ $color }}" @endif>
        <span class="text-[40cqw] leading-none">{{ $initials }}</span>
    </div>
</div>
