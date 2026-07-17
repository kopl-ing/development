@php
    use Illuminate\Support\Str;
    use Kopling\Core\Extension\Manager;
    use Kopling\Core\Ux\Context;
    use Kopling\Core\Ux\Editor\PlainTextExtractor;
    use Kopling\Core\Ux\SlotResolver;

    // The first post (the moment) is quotable into the reply dock the same way replies are
    // (see partials/reply.blade.php). Its quote id is the moment's own id -- distinct from any
    // reply id, so the dock tracks it as its own entry. $moment->body is the raw TipTap document
    // (see DocumentRenderer/PlainTextExtractor's own docblocks), not plain text -- extract it the
    // same way partials/reply.blade.php does for a reply's own quote preview.
    $momentId = (string) $moment->id;
    $momentAuthor = $moment->person?->name ?? __('kopling-discussions::messages.someone');
    $momentQuoteText = Str::limit(trim(preg_replace('/\s+/', ' ', PlainTextExtractor::extract((string) $moment->body))), 140);
@endphp
{{--
    The discussion page for one moment: the moment itself (through core's own card, so it
    keeps its tags/reactions/etc.), then the reply thread and a composer. Sits inside Community's
    own chrome (see k-core/views/community/chrome.blade.php) so it keeps the topbar/sidebar even
    though its route isn't registered under the Community portal's own route group -- Chrome
    resolves the Community portal itself, this page doesn't need to.
--}}
<x-k::community.chrome>
    <div class="flex flex-col gap-4">
        <div>
            <a href="/" class="btn btn-ghost btn-sm">&larr; {{ __('kopling-discussions::messages.back') }}</a>
        </div>

        <x-k::card.card :context="new Context(subject: $moment)" />

        @auth
            {{-- Quote the first post into the reply dock -- same event contract as a reply's
                 "+ Quote" (partials/reply.blade.php): dispatches kop-quote-toggle and reflects
                 its own state from the dock's kop-quotes-changed echo. --}}
            <div class="-mt-2">
                <button type="button" x-data="{ quoted: false }"
                        @kop-quotes-changed.window="quoted = $event.detail.ids.includes(@js($momentId))"
                        @click="$dispatch('kop-quote-toggle', { id: @js($momentId), author: @js($momentAuthor), text: @js($momentQuoteText) })"
                        :class="quoted ? 'text-primary font-semibold' : 'opacity-60 hover:opacity-100'"
                        class="text-xs font-semibold px-1.5 py-0.5 rounded hover:bg-base-200"
                        x-text="quoted ? @js(__('kopling-discussions::messages.unquote')) : @js(__('kopling-discussions::messages.quote'))">{{ __('kopling-discussions::messages.quote') }}</button>
            </div>
        @endauth

        <div class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">
                {{ trans_choice('kopling-discussions::messages.replies', $replies->count(), ['count' => $replies->count()]) }}
            </h2>

            <div id="replies-{{ $moment->id }}" class="flex flex-col gap-3">
                @foreach ($replies as $reply)
                    @include('kopling-discussions::partials.reply', ['reply' => $reply])
                @endforeach
            </div>

            {{-- A slot, not hardcoded markup -- lets a superseding extension (reply-dock) call
                 `Ux::remove('kopling-discussions::default-composer')` and own the one reply
                 surface itself, instead of only CSS-hiding a form whose editor still mounts.
                 The guest fallback stays here, unconditional on the slot -- see composer.blade.php's
                 own note on why "log in to reply" isn't part of what gets removed. --}}
            @foreach (SlotResolver::resolve('kopling-discussions::show.composer', app(Manager::class)->ux(), new Context(subject: $moment)) as $entry)
                <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
            @endforeach
            @guest
                <p class="text-sm opacity-70">{{ __('kopling-discussions::messages.login_to_reply') }}</p>
            @endguest
        </div>
    </div>
</x-k::community.chrome>
