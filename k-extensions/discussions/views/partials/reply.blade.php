@php
    use Illuminate\Support\Str;
    $name = $reply->person?->name ?? __('kopling-discussions::messages.someone');
    $rid = (string) $reply->id;

    // A reply body may open with one or more quote lines the reply dock prepended
    // ("> Author: text", blank-line separated) followed by the actual reply. Split them so
    // the quotes render as styled blockquotes instead of literal "> ..." plain text. There is
    // no markdown formatter in Kopling, so we parse the convention ourselves.
    $quotes = [];
    $bodyLines = preg_split('/\r?\n/', (string) $reply->body);
    $i = 0;
    while ($i < count($bodyLines)) {
        $line = $bodyLines[$i];
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            [$qa, $qt] = array_pad(preg_split('/:\s+/', $m[1], 2), 2, '');
            $quotes[] = $qt === '' ? ['author' => '', 'text' => $qa] : ['author' => $qa, 'text' => $qt];
            $i++;
        } elseif (trim($line) === '' && $quotes) {
            $i++; // blank separator between quotes / before the reply text
        } else {
            break;
        }
    }
    $text = trim(implode("\n", array_slice($bodyLines, $i)));
    // The "+ Quote" button should quote this reply's own words, not the quotes it embeds.
    $quoteText = Str::limit(trim(preg_replace('/\s+/', ' ', $text !== '' ? $text : (string) $reply->body)), 140);
@endphp
{{-- One reply. Rendered both in the initial thread and appended by the composer's htmx
     response, so a just-posted reply looks identical to a page-loaded one. --}}
<div class="chat chat-start">
    <div class="chat-header">
        {{ $name }}
        <time class="text-xs opacity-50">{{ $reply->created_at?->diffForHumans() }}</time>
    </div>
    <div class="chat-bubble">
        @foreach ($quotes as $q)
            <div style="border-inline-start:3px solid var(--color-primary,#2b4a9b);background:color-mix(in oklab,var(--color-primary,#2b4a9b) 12%,transparent);border-radius:var(--radius-field,.5rem);padding:5px 10px;margin-bottom:6px;font-size:.88em;opacity:.9;white-space:pre-wrap;">@if ($q['author'] !== '')<b>{{ $q['author'] }}</b>: @endif{{ $q['text'] }}</div>
        @endforeach
        @if ($text !== '')<div style="white-space:pre-wrap;">{{ $text }}</div>@endif
    </div>
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
