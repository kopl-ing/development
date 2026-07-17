@props(['data' => [], 'context' => null])
{{--
    Quote the moment itself into the reply dock, in the card footer next to Reply -- its own
    entry (not folded into engage.blade.php), the same reasoning `engage`/`teaser` already get
    separate entries for: each footer action is its own independently addressable/removable Ux
    entry, not a bundle another extension would have to take or leave whole. Same event contract
    as a reply's own "+ Quote" (partials/reply.blade.php): dispatches kop-quote-toggle and
    reflects its own state from the dock's kop-quotes-changed echo -- a harmless no-op if nothing
    listens (reply-dock not installed).

    Only on the moment's own discussion page (`$context->isRoute('moment')`): that's the only
    place a listener (reply-dock's composer) actually exists to receive the event, so it would be
    a dead button anywhere else the card renders (the feed, a rail).
--}}
@php
    $moment = $context?->getSubject();
@endphp
@auth
    @if ($moment && $context->isRoute('moment'))
        @php
            $momentId = (string) $moment->id;
            $momentAuthor = $moment->person?->name ?? __('kopling-discussions::messages.someone');
            $momentQuoteText = \Illuminate\Support\Str::limit(
                trim(preg_replace('/\s+/', ' ', \Kopling\Core\Ux\Editor\PlainTextExtractor::extract((string) $moment->body))),
                140
            );
        @endphp
        <button type="button" x-data="{ quoted: false }"
                @kop-quotes-changed.window="quoted = $event.detail.ids.includes(@js($momentId))"
                @click="$dispatch('kop-quote-toggle', { id: @js($momentId), author: @js($momentAuthor), text: @js($momentQuoteText) })"
                :class="quoted && 'text-primary'"
                class="btn btn-sm btn-ghost gap-1"
                x-text="quoted ? @js(__('kopling-discussions::messages.unquote')) : @js(__('kopling-discussions::messages.quote'))">{{ __('kopling-discussions::messages.quote') }}</button>
    @endif
@endauth
