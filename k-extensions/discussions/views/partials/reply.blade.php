@php
    use Kopling\Core\Ux\Context;
    use Kopling\Discussions\Reply;
@endphp
{{--
    One reply, rendered as its own extensible card -- the same Top/Body/Footer mechanism a
    Moment's own card uses (avatar/author/timestamp, then body, then a footer action row), just
    scoped to `Reply::TOP_SLOT`/`BODY_SLOT`/`FOOTER_SLOT` (see that constant's own docblock) so
    Moment-only registrations -- reactions, this same extension's own teaser/engage/quote-op --
    never bleed onto a reply that has none of those concepts. Rendered both in the initial thread
    and appended by the composer's htmx response, so a just-posted reply looks identical to a
    page-loaded one.

    `bg-base-300` is the only visual difference from a Moment's own `bg-base-100` card --
    everything else (border, card-body padding/layout) stays identical. Not `bg-base-200`: the
    page itself is `bg-base-200` (`portal/layout.blade.php`'s own `<body>`), so a card using that
    same shade would sit flush against the page with no visible surface at all. `base-300` is the
    next step down from the page background, reading as "nested/recessed" under the Moment's own
    `base-100` card, the standard three-tier daisyUI depth convention. `data-reply` marks it for
    reply-dock's own reply counter (`recount()` in dock.blade.php), which has no other way to
    count "how many replies are currently in the DOM".
--}}
<x-k::card.card
    :context="new Context(subject: $reply)"
    :top-slot="Reply::TOP_SLOT"
    :body-slot="Reply::BODY_SLOT"
    :footer-slot="Reply::FOOTER_SLOT"
    class="bg-base-300"
    data-reply="{{ $reply->id }}"
/>
