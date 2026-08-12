@php $items = $context->getSubjectPaginator(); @endphp

<div id="mail-message-list" class="flex flex-col h-full">
    @if ($items->isEmpty())
        <div class="p-6 text-center opacity-60">
            {{ __('kopling-mail-client::messages.no_messages') }}
        </div>
    @else
        <ul class="flex flex-col">
            @foreach ($items as $message)
                {{--
                    Each row is a thread's latest message (MailMessage::latestPerThread()), styled
                    like a Moment card -- daisyUI's own `card` classes, not Core's Card\* Ux
                    components (those are bound to real Moment models via Extend\Model/Context;
                    email threads are mail-client's own data, never actual moments/replies rows --
                    see email-client.md). Overrides the sidebar's own inherited hx-boost target:
                    opening a thread swaps only the reading pane, not the whole document body.
                --}}
                @php $isActive = $selected?->first()?->thread_id === $message->thread_id; @endphp
                {{--
                    Active state: a warning-tinted background (.kop-mail-thread-active, resources/css/app.css)
                    plus a chevron pinned to the row's own right edge -- inside the row, not
                    overflowing past it: the drawer-side list column scrolls (overflow-y-auto in
                    inbox.blade.php), and per the CSS overflow spec a non-'visible' overflow-y
                    forces overflow-x to compute as 'auto' too, so anything positioned outside the
                    row's own box would just get clipped instead of visibly "sticking out".

                    Blade computes $isActive for the initial page load only -- clicking a row only
                    swaps #mail-reading-pane, so kopMailSelectThread() (resources/js/app.js) is
                    what keeps this in sync on every click after that. The chevron is always
                    present in the DOM (just hidden) rather than conditionally rendered, so that
                    JS toggle has something to un-hide.
                --}}
                <li class="relative card card-compact rounded-none border-b border-base-200 last:border-b-0 {{ $isActive ? 'kop-mail-thread-active' : 'hover:bg-base-200/50' }}">
                    <a
                        href="{{ route('kopling-mail-client::mail/messages.show', $message) }}"
                        hx-get="{{ route('kopling-mail-client::mail/messages.show', $message) }}"
                        hx-target="#mail-reading-pane"
                        hx-swap="innerHTML"
                        hx-push-url="true"
                        onclick="kopMailSelectThread(this)"
                        class="card-body gap-1 py-3 pr-8"
                    >
                        <div class="flex items-center gap-2 w-full min-w-0">
                            <div class="avatar avatar-placeholder shrink-0">
                                <div class="bg-neutral text-neutral-content w-6 rounded-full text-xs">
                                    <span>{{ strtoupper(substr($message->account->label ?: $message->account->email_address, 0, 1)) }}</span>
                                </div>
                            </div>
                            <span class="truncate flex-1 {{ ! $message->flags?->seen ? 'font-semibold' : '' }}">{{ $message->from_name ?: $message->from_address }}</span>
                            @if ($message->thread_message_count > 1)
                                <span class="badge badge-sm badge-ghost">{{ $message->thread_message_count }}</span>
                            @endif
                            @if ($message->flags?->flagged)
                                <span class="text-warning" aria-label="{{ __('kopling-mail-client::messages.flagged') }}">&#9733;</span>
                            @endif
                            <span class="text-xs opacity-50 shrink-0">{{ $message->sent_at?->diffForHumans() }}</span>
                        </div>
                        <p class="truncate w-full {{ ! $message->flags?->seen ? 'font-semibold' : '' }}">{{ $message->subject ?: __('kopling-mail-client::messages.no_subject') }}</p>
                        @if ($message->snippet)
                            <p class="truncate w-full text-sm opacity-60">{{ $message->snippet }}</p>
                        @endif
                    </a>
                    <span data-thread-chevron class="absolute inset-y-0 right-2 flex items-center text-warning {{ $isActive ? '' : 'kop-mail-chevron-hidden' }}" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </li>
            @endforeach
        </ul>

        <div class="mt-auto p-2">
            <x-k::page.pagination :context="$context" target="#mail-message-list" />
        </div>
    @endif
</div>
