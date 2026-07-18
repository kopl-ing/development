@php use Kopling\Reactions\Reaction; @endphp
@props(['data' => [], 'context' => null, 'oob' => false])
{{--
    One moment's reaction rail, registered into `core::card.footer`. Rendered two ways, both
    reaching this same file: as the anonymous component `<x-kopling-reactions::rail>` the
    footer slot resolves, and directly by the toggle route as the htmx swap target. Reads
    `$context->subject` (the Moment) and `$context->actor` (the viewer) the same way every
    core card leaf reads its context -- never a loose array threaded through `$data`.

    Presentational only, mixing core's daisyUI/layout classes (btn/btn-primary/btn-ghost +
    flex/gap) with this extension's own explicit CSS (`css/app.css`, linked via
    `Extension::extendsPortals()`'s `->css()` -- see the head-assets outlet in
    `views/layouts/partials/head.blade.php`).

    `btn-xs` (shrunk from `btn-sm`) is deliberate contrast against the `vote` component
    registered just above this one -- its `btn-circle btn-lg` direction-colored buttons are
    meant to visually dominate over this rail's own calm, small pills, not just differ by fill.

    Every reaction here is `btn-circle` too, same shape family as `vote`'s buttons -- a pill
    (emoji + inline count side by side) reads as an ellipse, not a circle, the moment it carries
    a count. The count instead rides a daisyUI `indicator` badge at the circle's corner (only
    shown once one exists -- unlike `vote`, which always shows its count including 0; that
    distinction is deliberate, see `vote.blade.php`'s own docblock).
--}}
@php
    $moment = $context?->getSubject();
    $state = $moment ? Reaction::state($moment, $context?->actor) : null;
    // Emoji already claimed by this moment's tag-configured vote buttons (see the `vote`
    // component, registered just above this one) are excluded here -- a tag whose
    // `upvote_emoji` happens to be 👍 (the natural default, and already in PALETTE) would
    // otherwise produce two separate buttons toggling the same underlying reactions row.
    $voteEmoji = $moment ? array_column(Reaction::voteConfigFor($moment), 'emoji') : [];
@endphp
@if ($state)
    @php
        ['counts' => $counts, 'mine' => $mine, 'canReact' => $canReact] = $state;
        $hasAny = array_sum($counts) > 0;
    @endphp
    @if ($canReact || $hasAny)
        {{-- $oob: when the word form re-renders the strip, it also carries the rail back as
             an out-of-band swap so its counts stay in sync without a page reload. --}}
        <div id="reactions-{{ $moment->id }}" @if ($oob) hx-swap-oob="true" @endif class="flex flex-wrap items-center gap-2">
            @foreach (array_diff(Reaction::PALETTE, $voteEmoji) as $emoji)
                @php
                    $count = $counts[$emoji] ?? 0;
                    $active = in_array($emoji, $mine, true);
                @endphp
                @if ($canReact)
                    <div class="indicator">
                        @if ($count > 0)
                            <span class="indicator-item indicator-top indicator-end badge badge-xs tabular-nums">{{ $count }}</span>
                        @endif
                        <button type="button"
                                hx-post="{{ route('kopling-core::community/reactions.toggle', $moment->id) }}"
                                hx-vals='{{ json_encode(['emoji' => $emoji], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) }}'
                                hx-target="#reactions-{{ $moment->id }}"
                                hx-swap="outerHTML"
                                aria-pressed="{{ $active ? 'true' : 'false' }}"
                                title="{{ __('kopling-reactions::messages.react', ['emoji' => $emoji]) }}"
                                class="btn btn-xs btn-circle {{ $active ? 'btn-primary' : 'btn-ghost' }}">
                            <span aria-hidden="true" class="text-sm leading-none">{{ $emoji }}</span>
                        </button>
                    </div>
                @elseif ($count > 0)
                    {{-- Guests see the calm aggregate only -- counts, no toggles. --}}
                    <div class="indicator">
                        <span class="indicator-item indicator-top indicator-end badge badge-xs tabular-nums">{{ $count }}</span>
                        <span class="btn btn-xs btn-circle btn-ghost no-animation pointer-events-none">
                            <span aria-hidden="true" class="text-sm leading-none">{{ $emoji }}</span>
                        </span>
                    </div>
                @endif
            @endforeach
            @if ($canReact)
                {{-- Opens the one page-level picker modal against this moment via a window
                     event (see modal.blade). x-data gives an Alpine scope for $dispatch that
                     survives htmx rail swaps -- no store, since extension js can't register one
                     before core's Alpine.start(). --}}
                <button type="button" x-data
                        @click="$dispatch('kop-react-open', { url: '{{ route('kopling-core::community/reactions.word', $moment->id) }}', target: '#rwords-{{ $moment->id }}' })"
                        class="btn btn-xs btn-circle btn-ghost kop-radd"
                        title="{{ __('kopling-reactions::messages.add_reaction') }}"
                        aria-label="{{ __('kopling-reactions::messages.add_reaction') }}">＋</button>
            @endif
        </div>
    @endif
@endif
