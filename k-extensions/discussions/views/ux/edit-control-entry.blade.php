@if ($reply && $isOwnContent)
    <button type="button"
            hx-get="{{ route('kopling-core::community/discussions.reply.edit', $reply) }}"
            hx-target="closest .card" hx-swap="outerHTML"
            class="btn btn-ghost btn-sm w-full justify-start">
        {{ __('kopling-discussions::messages.edit') }}
    </button>
@endif
