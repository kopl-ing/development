@php use Kopling\Reactions\Reaction; @endphp
@props(['data' => [], 'context' => null, 'oob' => false])
{{--
    One moment's reaction rail, registered into `core::card.footer`. Rendered two ways, both
    reaching this same file: as the anonymous component `<x-kopling-reactions::rail>` the
    footer slot resolves, and directly by the toggle route as the htmx swap target. Reads
    `$context->subject` (the Moment) and `$context->actor` (the viewer) the same way every
    core card leaf reads its context -- never a loose array threaded through `$data`.

    Presentational only + safelisted daisyUI/layout classes: extension CSS can't be linked
    onto the page yet (no head-assets outlet), so this leans entirely on classes core already
    ships (btn/btn-primary/btn-ghost + flex/gap).
--}}
@php
    $moment = $context?->getSubject();
    $state = $moment ? Reaction::state($moment, $context?->actor) : null;
@endphp
@if ($state)
    @php
        ['counts' => $counts, 'mine' => $mine, 'canReact' => $canReact] = $state;
        $hasAny = array_sum($counts) > 0;
    @endphp
    @if ($canReact || $hasAny)
        {{-- $oob: when the word form re-renders the strip, it also carries the rail back as
             an out-of-band swap so its counts stay in sync without a page reload. --}}
        <div id="reactions-{{ $moment->id }}" @if ($oob) hx-swap-oob="true" @endif class="flex flex-wrap items-center gap-1.5">
            @foreach (Reaction::PALETTE as $emoji)
                @php
                    $count = $counts[$emoji] ?? 0;
                    $active = in_array($emoji, $mine, true);
                @endphp
                @if ($canReact)
                    <button type="button"
                            hx-post="{{ route('kopling-core::community/reactions.toggle', $moment->id) }}"
                            hx-vals='{{ json_encode(['emoji' => $emoji], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) }}'
                            hx-target="#reactions-{{ $moment->id }}"
                            hx-swap="outerHTML"
                            aria-pressed="{{ $active ? 'true' : 'false' }}"
                            title="{{ __('kopling-reactions::messages.react', ['emoji' => $emoji]) }}"
                            class="btn btn-sm rounded-full gap-1 {{ $active ? 'btn-primary' : 'btn-ghost' }}">
                        <span aria-hidden="true" class="text-base leading-none">{{ $emoji }}</span>
                        @if ($count > 0)
                            <span class="tabular-nums opacity-70">{{ $count }}</span>
                        @endif
                    </button>
                @elseif ($count > 0)
                    {{-- Guests see the calm aggregate only -- counts, no toggles. --}}
                    <span class="btn btn-sm btn-ghost no-animation pointer-events-none rounded-full gap-1">
                        <span aria-hidden="true" class="text-base leading-none">{{ $emoji }}</span>
                        <span class="tabular-nums opacity-70">{{ $count }}</span>
                    </span>
                @endif
            @endforeach
            @if ($canReact)
                {{-- Opens the one page-level picker modal against this moment via a window
                     event (see modal.blade). x-data gives an Alpine scope for $dispatch that
                     survives htmx rail swaps -- no store, since extension js can't register one
                     before core's Alpine.start(). --}}
                <button type="button" x-data
                        @click="$dispatch('kop-react-open', { url: '{{ route('kopling-core::community/reactions.word', $moment->id) }}', target: '#rwords-{{ $moment->id }}' })"
                        class="btn btn-sm btn-ghost rounded-full kop-radd"
                        title="{{ __('kopling-reactions::messages.add_reaction') }}"
                        aria-label="{{ __('kopling-reactions::messages.add_reaction') }}">＋</button>
            @endif
        </div>
    @endif
@endif
