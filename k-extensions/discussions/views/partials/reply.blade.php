@php
    use Illuminate\Support\Str;
    use Kopling\Core\Ux\Editor\PlainTextExtractor;

    $name = $reply->person?->name ?? __('kopling-discussions::messages.someone');
    $rid = (string) $reply->id;

    // There is no more plain-text "> Author: text" convention to parse here -- quoting now
    // inserts a real, directly-editable blockquote into the reply's own document instead of a
    // hidden prefixed line (see reply-dock's dock.blade.php), and $reply->body_html is already
    // fully rendered. The "+ Quote" button's own preview is simply this reply's whole
    // plain-text content -- there's no longer a structural way to tell "text this reply itself
    // typed" apart from "a blockquote it happens to contain", the same as for any other
    // blockquote.
    $quoteText = Str::limit(trim(preg_replace('/\s+/', ' ', PlainTextExtractor::extract((string) $reply->body))), 140);
@endphp
{{-- One reply. Rendered both in the initial thread and appended by the composer's htmx
     response, so a just-posted reply looks identical to a page-loaded one. --}}
<div class="chat chat-start">
    <div class="chat-header">
        {{ $name }}
        <time class="text-xs opacity-50">{{ $reply->created_at?->diffForHumans() }}</time>
    </div>
    {{-- $reply->body_html is rendered server-side by DocumentRenderer at write time -- never
         client-supplied HTML sanitized on the way out, see its docblock. --}}
    <div class="chat-bubble kop-content">{!! $reply->body_html !!}</div>
    @auth
        {{-- Multi-quote: toggles this reply into the reply dock's composer and flips its label
             to "− Quote". Event-driven (no Alpine store, which can't register before core's
             Alpine.start()): the dock owns the quote list and echoes the current id-set back via
             `kop-quotes-changed`, which this button listens for to track its own state. If the
             reply-dock extension isn't installed, nothing listens and the button is a harmless
             plain "+ Quote". --}}
        <div class="chat-footer mt-1">
            <button type="button" x-data="{ quoted: false }"
                    @kop-quotes-changed.window="quoted = $event.detail.ids.includes(@js($rid))"
                    @click="$dispatch('kop-quote-toggle', { id: @js($rid), author: @js($name), text: @js($quoteText) })"
                    :class="quoted ? 'text-primary font-semibold' : 'opacity-60 hover:opacity-100'"
                    class="text-xs font-semibold px-1.5 py-0.5 rounded hover:bg-base-200"
                    x-text="quoted ? @js(__('kopling-discussions::messages.unquote')) : @js(__('kopling-discussions::messages.quote'))">{{ __('kopling-discussions::messages.quote') }}</button>
        </div>
    @endauth
</div>
