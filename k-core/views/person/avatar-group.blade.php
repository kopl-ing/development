<span {{ $attributes->merge(['class' => "avatar-group {$spacing}"]) }}>
    @foreach ($avatars as $avatar)
        <x-k::person.avatar
            :name="$avatar['name'] ?? null"
            :color="$avatar['color'] ?? null"
            :presence="$avatar['presence'] ?? null"
            :size="$size"
            :mask="$mask"
            class="text-xs"
        />
    @endforeach
    @if ($overflow > 0)
        <div class="avatar avatar-placeholder">
            <div class="{{ $size }} {{ $mask ? "mask {$mask}" : 'rounded-full' }} bg-neutral text-neutral-content text-xs">
                <span>+{{ $overflow }}</span>
            </div>
        </div>
    @endif
</span>
