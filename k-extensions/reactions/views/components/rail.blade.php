@php use Kopling\Core\Content\Moment; use Kopling\Reactions\Reaction; @endphp
@props(['data' => [], 'context' => null, 'oob' => false])
{{--
    One reactable's reaction rail -- a Moment, or (once discussions is installed) a Reply --
    registered into both `core::card.footer` and `kopling-discussions::reply.footer` (see
    `Extension::ux()`). Rendered two ways, both reaching this same file: as the anonymous
    component `<x-kopling-reactions::rail>` the footer slot resolves, and directly by the
    toggle route as the htmx swap target. Reads `$context->subject` (the reactable) and
    `$context->actor` (the viewer) the same way every core card leaf reads its context -- never
    a loose array threaded through `$data`.

    Presentational only, mixing core's daisyUI/layout classes (btn/btn-primary/btn-ghost +
    flex/gap) with this extension's own explicit CSS (`css/app.css`, linked via
    `Extension::extendsPortals()`'s `->css()` -- see the head-assets outlet in
    `views/layouts/partials/head.blade.php`).

    `btn-xs` (shrunk from `btn-sm`) is deliberate contrast against the `vote` component
    registered just above this one on a Moment's own footer -- its `btn-circle btn-lg`
    direction-colored buttons are meant to visually dominate over this rail's own calm, small
    pills, not just differ by fill. `vote` is Moment-only (tag-configured, and a Reply carries
    no tags -- see `Reaction::voteConfigFor()`'s own docblock), never registered on a Reply's
    footer at all, which is why `$voteEmoji` below only ever excludes anything when `$reactable`
    actually is a `Moment`.

    Every reaction here is `btn-circle` too, same shape family as `vote`'s buttons -- a pill
    (emoji + inline count side by side) reads as an ellipse, not a circle, the moment it carries
    a count. The count instead rides a daisyUI `indicator` badge at the circle's corner (only
    shown once one exists -- unlike `vote`, which always shows its count including 0; that
    distinction is deliberate, see `vote.blade.php`'s own docblock).

    Only emoji someone has actually reacted with render here -- `PALETTE` entries sitting at a
    0 count are skipped entirely, `canReact` or not, so a signed-in viewer no longer sees all of
    `PALETTE` rendered as if they were live toggles for reactions nobody's made yet. `vote`'s own
    buttons are the deliberate exception (see its own docblock: "a configured direction always
    renders, including a 0 count") -- unaffected here since they're already excluded from this
    loop entirely via `$voteEmoji`, rendered by `vote.blade.php` instead. Starting a reaction
    nothing has yet is still possible for a signed-in viewer, just through the "+" opener's
    picker modal (`Reaction::PALETTE` in full there) rather than a live-but-inert button in this
    row.

    `$count` (from `Reaction::state()`) only tallies *wordless* reactions for each emoji -- a
    worded one already gets its own chip from `words.blade.php`, merged into this same row (see
    `Card` footer's own decisions.md entry). At `$count === 0` this emoji renders nothing here at
    all, even if the viewer's own reaction for it happens to be worded -- the whole point is that
    a worded reaction lives *only* in its chip, never also as a rail pill, so the emoji itself
    never appears twice. This does mean a worded-only reaction has no rail button to remove it
    through; that's an accepted, known gap (not solved here) rather than reintroducing the
    duplication just to keep a removal affordance nobody asked for.
--}}
@php
    $reactable = $context?->getSubject();
    $state = $reactable ? Reaction::state($reactable, $context?->actor) : null;
    // Emoji already claimed by a Moment's tag-configured vote buttons (see the `vote`
    // component, registered just above this one) are excluded here -- a tag whose
    // `upvote_emoji` happens to be 👍 (the natural default, and already in PALETTE) would
    // otherwise produce two separate buttons toggling the same underlying reactions row.
    // `voteConfigFor()` only accepts a `Moment` -- a Reply has no tags, hence no vote config,
    // so this stays an empty exclusion list for one rather than calling it with the wrong type.
    $voteEmoji = $reactable instanceof Moment ? array_column(Reaction::voteConfigFor($reactable), 'emoji') : [];
@endphp
@if ($state)
    @php
        ['counts' => $counts, 'mine' => $mine, 'canReact' => $canReact] = $state;
        $hasAny = array_sum($counts) > 0;
    @endphp
    @if ($canReact || $hasAny)
        {{-- $oob: when the word form re-renders the strip, it also carries the rail back as
             an out-of-band swap so its counts stay in sync without a page reload. --}}
        <div id="reactions-{{ $reactable->id }}" @if ($oob) hx-swap-oob="true" @endif class="flex flex-nowrap items-center gap-2">
            @foreach (array_diff(Reaction::PALETTE, $voteEmoji) as $emoji)
                @php
                    $count = $counts[$emoji] ?? 0;
                    $active = in_array($emoji, $mine, true);
                @endphp
                @continue ($count === 0)
                @if ($canReact)
                    <div class="indicator shrink-0">
                        <span class="indicator-item indicator-top indicator-end badge badge-xs tabular-nums">{{ $count }}</span>
                        <button type="button"
                                hx-post="{{ route('kopling-core::community/reactions.toggle', ['type' => $reactable->getMorphClass(), 'id' => $reactable->id]) }}"
                                hx-vals='{{ json_encode(['emoji' => $emoji], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) }}'
                                hx-target="#reactions-{{ $reactable->id }}"
                                hx-swap="outerHTML"
                                aria-pressed="{{ $active ? 'true' : 'false' }}"
                                title="{{ __('kopling-reactions::messages.react', ['emoji' => $emoji]) }}"
                                class="btn btn-xs btn-circle {{ $active ? 'btn-primary' : 'btn-ghost' }}">
                            <span aria-hidden="true" class="text-sm leading-none">{{ $emoji }}</span>
                        </button>
                    </div>
                @else
                    {{-- Guests see the calm aggregate only -- counts, no toggles. --}}
                    <div class="indicator shrink-0">
                        <span class="indicator-item indicator-top indicator-end badge badge-xs tabular-nums">{{ $count }}</span>
                        <span class="btn btn-xs btn-circle btn-ghost no-animation pointer-events-none">
                            <span aria-hidden="true" class="text-sm leading-none">{{ $emoji }}</span>
                        </span>
                    </div>
                @endif
            @endforeach
            @if ($canReact)
                {{-- Opens the one page-level picker modal against this reactable via a window
                     event (see modal.blade). x-data gives an Alpine scope for $dispatch that
                     survives htmx rail swaps -- no store, since extension js can't register one
                     before core's Alpine.start(). --}}
                <button type="button" x-data
                        @click="$dispatch('kop-react-open', { url: '{{ route('kopling-core::community/reactions.word', ['type' => $reactable->getMorphClass(), 'id' => $reactable->id]) }}', target: '#rwords-{{ $reactable->id }}' })"
                        class="btn btn-xs btn-circle btn-ghost kop-radd shrink-0"
                        title="{{ __('kopling-reactions::messages.add_reaction') }}"
                        aria-label="{{ __('kopling-reactions::messages.add_reaction') }}">＋</button>
            @endif
        </div>
    @endif
@endif
