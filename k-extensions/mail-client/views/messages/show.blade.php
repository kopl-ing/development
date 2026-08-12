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

    @if ($message->body_text !== null)
        {{--
            Plain text only, deliberately -- body_html is stored (see email-client.md's Data
            model) but not rendered here: email HTML is about as untrusted as content gets, and
            the only sanitizer in this codebase (activitypub's InboundHtmlSanitizer) is scoped
            too narrowly for real email markup (no tables/images/inline styles, which most HTML
            email relies on). Rendering it unescaped without a real one would be a genuine XSS
            hole, not a shortcut worth taking.
        --}}
        <pre class="whitespace-pre-wrap font-sans text-sm">{{ $message->body_text }}</pre>
    @else
        <div class="alert alert-info">
            <span>{{ __('kopling-mail-client::messages.body_not_synced') }}</span>
        </div>
    @endif
</div>
