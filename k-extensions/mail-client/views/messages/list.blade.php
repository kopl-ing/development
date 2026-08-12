@php $items = $context->getSubjectPaginator(); @endphp

<div id="mail-message-list" class="flex flex-col h-full">
    @if ($items->isEmpty())
        <div class="p-6 text-center opacity-60">
            {{ __('kopling-mail-client::messages.no_messages') }}
        </div>
    @else
        <ul class="menu w-full p-0">
            @foreach ($items as $message)
                {{--
                    Overrides the sidebar's own inherited `hx-boost` target: selecting a message
                    swaps only the reading pane, not the whole document body. The drawer-closing
                    hook lives on #mail-reading-pane itself (see inbox.blade.php), not here --
                    `htmx:after:settle` fires on the swapped-in element, never on the triggering
                    element in a sibling subtree like this row's own `<a>` (CLAUDE.md gotcha).
                --}}
                <li>
                    <a
                        href="{{ route('kopling-mail-client::mail/messages.show', $message) }}"
                        hx-get="{{ route('kopling-mail-client::mail/messages.show', $message) }}"
                        hx-target="#mail-reading-pane"
                        hx-swap="innerHTML"
                        hx-push-url="true"
                        class="flex flex-col items-start gap-1 py-3 border-b border-base-200 {{ $selected?->is($message) ? 'menu-active' : '' }} {{ ! $message->flags?->seen ? 'font-semibold' : '' }}"
                    >
                        <div class="flex items-center gap-2 w-full">
                            <div class="avatar avatar-placeholder">
                                <div class="bg-neutral text-neutral-content w-5 rounded-full text-[10px]">
                                    <span>{{ strtoupper(substr($message->account->label ?: $message->account->email_address, 0, 1)) }}</span>
                                </div>
                            </div>
                            <span class="truncate flex-1">{{ $message->from_name ?: $message->from_address }}</span>
                            @if ($message->flags?->flagged)
                                <span class="text-warning" aria-label="{{ __('kopling-mail-client::messages.flagged') }}">&#9733;</span>
                            @endif
                        </div>
                        <span class="truncate w-full text-sm">{{ $message->subject ?: __('kopling-mail-client::messages.no_subject') }}</span>
                        @if ($message->snippet)
                            <span class="truncate w-full text-xs opacity-60 font-normal">{{ $message->snippet }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-auto p-2">
            <x-k::page.pagination :context="$context" target="#mail-message-list" />
        </div>
    @endif
</div>
