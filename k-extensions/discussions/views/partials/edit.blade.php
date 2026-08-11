{{-- The edit form, swapped in over the reply's own `.card` (see
     ux/edit-control-entry.blade.php's own `hx-target="closest .card"`) -- same outer classes
     `partials/reply.blade.php` uses, so editing reads as "the same card, now in edit mode".
     `data-reply` is kept so reply-dock's own counter (`recount()` in dock.blade.php) doesn't
     miscount while a reply is being edited. --}}
<div class="card bg-base-100 card-dash" data-reply="{{ $reply->id }}">
    <form hx-post="{{ route('kopling-core::community/discussions.reply.update', $reply) }}"
          hx-target="closest .card" hx-swap="outerHTML"
          class="divide-y divide-base-content/10">
        @csrf

        <div class="px-4 py-3 sm:p-6">
            <x-k::editor name="body" :value="$reply->body" placeholder="{{ __('kopling-discussions::messages.composer_placeholder') }}" />
        </div>

        <div class="flex items-center justify-end gap-2 px-4 py-3 sm:px-6">
            <button type="button"
                    hx-get="{{ route('kopling-core::community/discussions.reply.show', $reply) }}"
                    hx-target="closest .card" hx-swap="outerHTML"
                    class="btn btn-ghost btn-sm">
                {{ __('kopling-discussions::messages.cancel') }}
            </button>
            <button type="submit" class="btn btn-primary btn-sm">
                {{ __('kopling-discussions::messages.save') }}
            </button>
        </div>
    </form>
</div>
