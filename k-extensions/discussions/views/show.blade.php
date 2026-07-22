@php
    use Kopling\Core\Extension\Manager;
    use Kopling\Core\Ux\Context;
    use Kopling\Core\Ux\SlotResolver;
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

        <div class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">
                {{ trans_choice('kopling-discussions::messages.replies', $replies->total(), ['count' => $replies->total()]) }}
            </h2>

            {{-- `#replies-wrapper-{id}` is the pagination's own htmx target/select (see
                 `Pagination`'s own docblock) -- `#replies-{id}` itself stays untouched inside
                 it, since the reply composer's own `hx-target="#replies-{id}"` (dock.blade.php,
                 composer.blade.php) already appends a freshly-posted reply there directly and
                 knows nothing about this wrapper. `data-total-replies`/`data-page-base-index`
                 let reply-dock's own scrubber (dock.blade.php) re-sync itself after a boosted
                 page change swaps this whole wrapper in fresh -- see that file's own
                 `syncFromRepliesPage()` for why it can't just trust its own initial numbers. --}}
            <div
                id="replies-wrapper-{{ $moment->id }}"
                data-total-replies="{{ $replies->total() }}"
                data-page-base-index="{{ ($replies->currentPage() - 1) * $replies->perPage() }}"
            >
                <div id="replies-{{ $moment->id }}" class="flex flex-col gap-3">
                    @foreach ($replies as $reply)
                        @include('kopling-discussions::partials.reply', ['reply' => $reply])
                    @endforeach
                </div>

                <x-k::page.pagination :context="$context" target="#replies-wrapper-{{ $moment->id }}" />
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
