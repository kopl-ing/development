@props(['data' => [], 'context' => null])
{{--
    Quote this reply into the reply dock -- registered into `Reply::FOOTER_SLOT`. Same event
    contract as `quote-op.blade.php` (the Moment's own "+ Quote"): dispatches kop-quote-toggle and
    reflects its own state from the dock's kop-quotes-changed echo -- a harmless no-op if nothing
    listens (reply-dock not installed). Unlike `quote-op`, no `isRoute('moment')` check: a reply
    only ever renders on its own moment's discussion page in the first place, never a feed/rail.

    `ml-auto shrink-0`: reactions now registers its own `rail`/`words` into this same slot ahead
    of this entry (`->before('kopling-discussions::quote-reply')`, see reactions'
    `Extension::ux()`), so this pins to the row's own end the same way Moment footer's `engage`/
    `quote-op` already do, regardless of how wide that reactions cluster gets.
--}}
@php
    $reply = $context?->getSubject();
@endphp
@auth
    @if ($reply)
        @php
            $replyId = (string) $reply->id;
            $replyAuthor = $reply->person?->name ?? __('kopling-discussions::messages.someone');
            $replyQuoteText = \Illuminate\Support\Str::limit(
                trim(preg_replace('/\s+/', ' ', \Kopling\Core\Ux\Editor\PlainTextExtractor::extract((string) $reply->body))),
                140
            );
        @endphp
        <button type="button" x-data="{ quoted: false }"
                @kop-quotes-changed.window="quoted = $event.detail.ids.includes(@js($replyId))"
                @click="$dispatch('kop-quote-toggle', { id: @js($replyId), author: @js($replyAuthor), text: @js($replyQuoteText) })"
                :class="quoted ? 'text-primary font-semibold' : 'opacity-60 hover:opacity-100'"
                class="text-xs font-semibold px-1.5 py-0.5 rounded hover:bg-base-200 ml-auto shrink-0"
                x-text="quoted ? @js(__('kopling-discussions::messages.unquote')) : @js(__('kopling-discussions::messages.quote'))">{{ __('kopling-discussions::messages.quote') }}</button>
    @endif
@endauth
