{{--
    One card per message in the thread (oldest first, MailMessage::inThread()) -- visually
    modeled on a Moment + its Replies, but built entirely from mail-client's own data: these are
    never real Moment/Reply rows (see email-client.md's "no promotion feature for now" note --
    an email thread staying a Moment would be visible to the whole community and reachable by
    every extension that hooks into any Moment, which is wrong for private correspondence).
--}}
<div class="flex flex-col gap-4 p-4">
    @foreach ($thread as $message)
        {{-- min-w-0: a flex item (this card, inside the outer flex-col wrapper) defaults to
             min-width:auto, letting wide content (the <pre> body) force it past its container's
             width even with break-words on the content itself -- same fix card/title.blade.php
             already documents for the identical class of bug. --}}
        <div class="card bg-base-100 border border-base-200 shadow-sm min-w-0" id="mail-message-{{ $message->id }}">
            <div class="card-body gap-2 py-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="avatar avatar-placeholder shrink-0">
                            <div class="bg-neutral text-neutral-content w-8 rounded-full">
                                <span>{{ strtoupper(substr($message->from_name ?: $message->from_address ?: '?', 0, 1)) }}</span>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium truncate">{{ $message->from_name ?: $message->from_address }}</p>
                            <p class="text-xs opacity-50 truncate">
                                {{ $message->from_address }} &middot; {{ $message->sent_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <span class="badge badge-ghost badge-sm shrink-0">{{ $message->account->label ?: $message->account->email_address }}</span>
                </div>

                @if ($loop->first)
                    <h2 class="text-lg font-semibold break-words">{{ $message->subject ?: __('kopling-mail-client::messages.no_subject') }}</h2>
                @endif

                @if ($message->body_text !== null)
                    {{--
                        Plain text only -- body_html isn't rendered here; see email-client.md's
                        sanitization note. whitespace-pre-wrap alone still respects
                        overflow-wrap: normal, so a single long unbroken run (a long URL, no
                        spaces) doesn't wrap and pushes the card wider than its container --
                        break-words (overflow-wrap: break-word) is what actually forces it to
                        wrap once there's no other soft-wrap point on the line.
                    --}}
                    <pre class="whitespace-pre-wrap break-words font-sans text-sm">{{ $message->body_text }}</pre>
                @else
                    <div class="alert alert-info alert-sm">
                        <span>{{ __('kopling-mail-client::messages.body_not_synced') }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
