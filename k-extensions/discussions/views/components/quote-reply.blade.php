@props(['data' => [], 'context' => null])
{{--
    Quote this reply into the reply dock -- registered into `Reply::FOOTER_SLOT`. Same event
    contract as `quote-op.blade.php` (the Moment's own "+ Quote"): dispatches kop-quote-toggle and
    reflects its own state from the dock's kop-quotes-changed echo -- a harmless no-op if nothing
    listens (reply-dock not installed). Same reasoning as `quote-op`'s own `isRoute('moment')`
    guard -- the dock only ever listens on the reply's own moment's discussion page, so this is a
    dead button anywhere else the reply itself renders (profile's own "Replies" tab included).
    `Context::isRoute()` can't express this directly (it only compares against its own `$subject`,
    the reply here, not the reply's moment), hence the inline route-parameter check below instead
    -- compares `moment_id` rather than the `moment` relation so this never forces a per-reply
    lazy-load on pages (the discussion thread) that don't eager-load it.

    `ml-auto shrink-0`: reactions now registers its own `rail`/`words` into this same slot ahead
    of this entry (`->before('kopling-discussions::quote-reply')`, see reactions'
    `Extension::ux()`), so this pins to the row's own end the same way Moment footer's `engage`/
    `quote-op` already do, regardless of how wide that reactions cluster gets.
--}}
@php
    $reply = $context?->getSubject();
    $routeMoment = request()->route('moment');
    $onOwnDiscussionPage = $reply && $routeMoment instanceof \Kopling\Core\Content\Moment
        && $routeMoment->getKey() === $reply->moment_id;
@endphp
@auth
    @if ($reply && $onOwnDiscussionPage)
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
