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

            <div id="replies-{{ $moment->id }}" class="flex flex-col gap-3">
                @foreach ($replies as $reply)
                    @include('kopling-discussions::partials.reply', ['reply' => $reply])
                @endforeach
            </div>

            <x-k::page.pagination :context="$context" />

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
