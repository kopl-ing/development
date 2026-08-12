<div class="p-6 flex flex-col gap-4">
    <div>
        <h2 class="text-xl font-semibold">{{ $message->subject ?: __('kopling-mail-client::messages.no_subject') }}</h2>
        <p class="text-sm opacity-70">
            {{ $message->from_name ?: $message->from_address }}
            <span class="opacity-50">&lt;{{ $message->from_address }}&gt;</span>
        </p>
        <p class="text-xs opacity-50">
            {{ $message->sent_at?->diffForHumans() }} &middot; {{ $message->account->label ?: $message->account->email_address }}
        </p>
    </div>

    <div class="divider my-0"></div>

    {{-- No IMAP/SMTP sync layer exists yet (see email-client.md's "Protocol layer" section) --
         bodies are fetched on demand from the server, never mirrored locally, so there is
         nothing to render here until that layer is built. --}}
    <div class="alert alert-info">
        <span>{{ __('kopling-mail-client::messages.body_not_synced') }}</span>
    </div>
</div>
