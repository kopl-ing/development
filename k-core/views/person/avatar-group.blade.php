<span {{ $attributes->merge(['class' => "avatar-group $spacing"]) }}>
    @foreach ($avatars as $avatar)
        <x-k::person.avatar
            :name="$avatar['name'] ?? null"
            :color="$avatar['color'] ?? null"
            :size="$size"
        />
    @endforeach
    @if ($overflow > 0)
        <x-k::person.avatar :size="$size" initials="+{{ $overflow }}" />
    @endif
</span>
