@php use Kopling\Reactions\Reaction; @endphp
@props(['data' => [], 'context' => null])
{{--
    A moment's dedicated vote buttons -- one per (direction, emoji) pair its tags configure
    (see Reaction::voteConfigFor, which also guarantees up-before-down ordering), registered
    into `core::card.footer` ahead of the generic rail so voting sits "sticky above" the calm
    emoji aggregate. Unlike the rail (which only shows a count once one exists), a configured
    direction always renders, including a 0 count -- voting is an intentional per-tag feature,
    not a passive aggregate. Same toggle/state plumbing as the rail (`Reaction::state`, the
    same `reactions` table row).

    Deliberately styled to stand out from the rail's own small ghost pills: `btn-circle btn-lg`
    (vs. the rail's shrunk `btn-xs`) plus a direction color -- `primary` for up, `secondary` for
    down -- solid when it's the viewer's own vote, outlined otherwise, so direction and "did I
    vote" read as two independent signals rather than one generic active/inactive fill. The
    count rides along as a daisyUI `indicator` badge at the button's corner instead of inline
    text, since a circular button has no natural place for a second line of content.
--}}
@php
    $moment = $context?->getSubject();
    $pairs = $moment ? Reaction::voteConfigFor($moment) : [];
@endphp
@if (! empty($pairs))
    @php
        $state = Reaction::state($moment, $context?->actor);
        $counts = $state['counts'];
        $mine = $state['mine'];
        $canVote = $state['canReact'];
    @endphp
    <div id="votes-{{ $moment->id }}" class="flex shrink-0 flex-nowrap items-center gap-3">
        @foreach ($pairs as $pair)
            @php
                $emoji = $pair['emoji'];
                $count = $counts[$emoji] ?? 0;
                $active = in_array($emoji, $mine, true);
                $color = $pair['direction'] === 'up' ? 'primary' : 'secondary';
            @endphp
            <div class="indicator">
                <span class="indicator-item indicator-top indicator-end badge badge-sm badge-{{ $color }} tabular-nums">{{ $count }}</span>
                @if ($canVote)
                    <button type="button"
                            hx-post="{{ route('kopling-core::community/reactions.vote', $moment->id) }}"
                            hx-vals='{{ json_encode(['emoji' => $emoji], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) }}'
                            hx-target="#votes-{{ $moment->id }}"
                            hx-swap="outerHTML"
                            aria-pressed="{{ $active ? 'true' : 'false' }}"
                            title="{{ __('kopling-reactions::messages.vote', ['emoji' => $emoji]) }}"
                            class="btn btn-circle btn-lg {{ $active ? 'btn-'.$color : 'btn-outline btn-'.$color }}">
                        <span aria-hidden="true" class="text-xl leading-none">{{ $emoji }}</span>
                    </button>
                @else
                    {{-- Guests see the calm count only -- no toggles, same rule as the rail. --}}
                    <span class="btn btn-circle btn-lg btn-outline btn-{{ $color }} no-animation pointer-events-none">
                        <span aria-hidden="true" class="text-xl leading-none">{{ $emoji }}</span>
                    </span>
                @endif
            </div>
        @endforeach
    </div>
@endif
