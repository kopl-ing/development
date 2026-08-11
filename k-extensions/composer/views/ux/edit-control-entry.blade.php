@if ($moment && $isOwnContent)
    <button type="button"
            hx-get="{{ route('kopling-core::community/compose.edit', $moment) }}"
            hx-target="closest .card" hx-swap="outerHTML"
            class="btn btn-ghost btn-sm w-full justify-start">
        {{ __('kopling-composer::messages.edit') }}
    </button>
@endif
