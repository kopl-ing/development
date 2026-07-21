{{--
    `$url` is `$context->getSubjectUrl()` (see `Card`'s own docblock) -- when set, the whole
    card is clickable via a stretched-link overlay: one invisible `<a>` covering the entire
    box, `z-0` so it sits beneath everything real. The content wrapper below is bumped to
    `relative z-10` so it (and everything inside Top/Body/Footer -- Title's own link, footer
    buttons, the Control dropdown) keeps sitting visually and interactively above the overlay,
    without any of those components needing their own z-index. `Badges` already carries its
    own `z-10`, so it's unaffected either way.

    The outer `aura aura-glow` wrapper is daisyUI's own glow-ring component, which only paints
    anything once its `currentColor` isn't transparent -- `text-transparent` at rest, `hover:
    text-primary` lit up on hover, is what makes the whole ring fade in rather than toggling
    `opacity` (which would fade the *card itself*, nested inside, right along with it; `.aura`'s
    own children are `position: relative; z-index: 1`, so `.card` still paints above the glow's
    blurred `:before`/`:after` layers regardless). No `group` needed here -- hovering any
    descendant (the `.card` inside) already counts as hovering this wrapper too, plain CSS
    `:hover` bubbling. `block w-full` overrides `.aura`'s own `display: inline-block` default,
    which would otherwise shrink-wrap the ring to the card's content width instead of the row.

    `.card` itself needs its own explicit `text-base-content` -- `color` inherits, so without
    resetting it here every bit of text inside (title, body, timestamps, ...) would inherit the
    wrapper's `text-transparent`/`hover:text-primary` right along with it, going fully invisible
    at rest instead of just the glow ring. `Title`'s own `group-hover:text-primary` and the
    caret's `group-hover:text-primary` both still win over this reset on hover, same as any
    Tailwind utility beats an inherited value.

    The trailing caret only ever signals "this opens something" -- `pointer-events-none` so a
    click that happens to land exactly on it still falls through to the stretched-link overlay
    beneath rather than hitting an inert `<svg>`.
--}}
<div @class(['aura aura-glow block w-full text-transparent transition-colors duration-300 hover:text-primary' => $url])>
    <div {{ $attributes->merge(['class' => "card bg-base-100 text-base-content $classes"]) }}>
        @if ($url)
            <a href="{{ $url }}" class="absolute inset-0 z-0" aria-label="{{ __('kopling-core::community.open') }}"></a>
            <x-k::icon
                name="kopling-core::open"
                class="pointer-events-none absolute right-4 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-base-content/40 transition-colors text-primary hidden group-hover:block"
            />
        @endif
        <x-k::card.badges :context="$context" :slot="$badgesSlot" />
        <div class="relative z-10 divide-y divide-base-content/10 overflow-hidden rounded-[inherit]">
            <x-k::card.top :context="$context" :slot="$topSlot" />
            <x-k::card.body :context="$context" :slot="$bodySlot" />
            <x-k::card.footer :context="$context" :slot="$footerSlot" />
        </div>
    </div>
</div>
