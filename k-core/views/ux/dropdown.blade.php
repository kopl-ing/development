<button type="button" popovertarget="{{ $id }}" style="anchor-name:--{{ $id }}"
        {{ $attributes->merge(['class' => 'btn btn-ghost btn-circle btn-sm']) }}
        aria-label="{{ $label }}" aria-haspopup="menu">
    {{ $trigger }}
</button>
<ul class="dropdown {{ $align }} menu bg-base-100 rounded-box z-10 w-52 p-2 shadow-sm" popover
    id="{{ $id }}" style="position-anchor:--{{ $id }}" role="menu">
    {{ $slot }}
</ul>
