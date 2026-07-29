{{--
    `data-href` (only when `$url` is set) marks the whole card as one click target for the
    generic delegated listener in `app.js` -- clicking anywhere that isn't already inside a real
    interactive element (a link, button, etc.) or a text selection navigates there. No stretched
    invisible `<a>`/`pointer-events` fighting needed: everything inside the card behaves
    normally, and nothing has to be added to an allowlist to stay clickable. The card's own real
    link -- `Card\Title`'s `<a href="$url">` -- is what makes this a progressive enhancement
    rather than the only way to reach the destination.

    `.aura` (daisyUI) requires `.card` as a direct child to glow around it -- the two can't
    collapse onto one element. `text-transparent`/`hover:text-primary` on that wrapper controls
    the glow's `currentColor` without touching `.card`'s own text, which resets it back via its
    own `text-base-content`.

    `.card`'s own `bg-base-100` stays uncontested; `$classes` (`RenderingCard` contributions like
    Pin's `outline-{color} bg-{color}/5`) render on a separate decoration `<div>` instead, so a
    contributed `background-color` layers over `.card`'s backdrop instead of replacing it
    outright. `outline-2 outline-offset-2 outline-transparent` on that div reserves the
    width/style/offset an `outline-{color}` contribution only sets the color for.
--}}
<div @class(['aura aura-glow aura-xs block w-full text-transparent transition-colors duration-300 hover:text-primary' => $url])>
    <div
        {{ $attributes->merge(['class' => 'card bg-base-100 text-base-content'.($url ? ' group cursor-pointer' : '')]) }}
        @if ($url) data-href="{{ $url }}" @endif
    >
        <div class="pointer-events-none absolute inset-0 z-0 rounded-[inherit] outline-2 outline-offset-2 outline-transparent {{ $classes }}"></div>
        @if ($url)
            <x-k::icon
                name="kopling-core::open"
                class="pointer-events-none absolute left-full top-1/2 z-10 ml-2 h-4 w-4 -translate-y-1/2 text-base-content/40 transition-colors text-primary hidden group-hover:block"
            />
        @endif
        <x-k::card.badges :context="$context" :slot="$badgesSlot" />
        <div class="relative z-10 divide-y divide-base-content/10 overflow-hidden rounded-[inherit]">
            <x-k::card.top :context="$context" :slot="$topSlot" :control-slot="$controlSlot" />
            <x-k::card.body :context="$context" :slot="$bodySlot" />
            <x-k::card.footer :context="$context" :slot="$footerSlot" />
        </div>
    </div>
</div>
