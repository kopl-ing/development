{{--
    `$url` set -> the whole card links out via an invisible `z-0` stretched `<a>`. The content
    wrapper below needs `pointer-events-none` (paired with `[&_a]:`/`[&_button]:` etc.
    `pointer-events-auto`) so its non-interactive parts (body text, padding) let clicks fall
    through to that anchor -- a plain `relative z-10` alone still wins every hit-test in its own
    subtree, interactive or not.

    The `aura aura-glow` wrapper uses `text-transparent`/`hover:text-primary` rather than
    `opacity`, since opacity would fade `.card` itself along with the glow. `.card` needs its own
    `text-base-content` to stop that wrapper's color from inheriting into all its text.

    `.card`'s own `bg-base-100` stays uncontested; `$classes` (`RenderingCard` contributions like
    Pin's `outline-{color} bg-{color}/5`) render on a separate decoration `<div>` instead, so a
    contributed `background-color` layers over `.card`'s backdrop instead of replacing it
    outright. `outline-2 outline-offset-2 outline-transparent` on that div reserves the
    width/style/offset an `outline-{color}` contribution only sets the color for.
--}}
<div @class(['aura aura-glow aura-xs block w-full text-transparent transition-colors duration-300 hover:text-primary' => $url])>
    <div {{ $attributes->merge(['class' => 'card bg-base-100 text-base-content'.($url ? ' group cursor-pointer' : '')]) }}>
        <div class="pointer-events-none absolute inset-0 z-0 rounded-[inherit] outline-2 outline-offset-2 outline-transparent {{ $classes }}"></div>
        @if ($url)
            <a href="{{ $url }}" class="absolute inset-0 z-0" aria-label="{{ __('kopling-core::community.open') }}"></a>
            <x-k::icon
                name="kopling-core::open"
                class="pointer-events-none absolute left-full top-1/2 z-10 ml-2 h-4 w-4 -translate-y-1/2 text-base-content/40 transition-colors text-primary hidden group-hover:block"
            />
        @endif
        <x-k::card.badges :context="$context" :slot="$badgesSlot" />
        <div class="pointer-events-none relative z-10 divide-y divide-base-content/10 overflow-hidden rounded-[inherit] [&_a]:pointer-events-auto [&_button]:pointer-events-auto [&_input]:pointer-events-auto [&_label]:pointer-events-auto [&_select]:pointer-events-auto [&_textarea]:pointer-events-auto [&_[popovertarget]]:pointer-events-auto">
            <x-k::card.top :context="$context" :slot="$topSlot" />
            <x-k::card.body :context="$context" :slot="$bodySlot" />
            <x-k::card.footer :context="$context" :slot="$footerSlot" />
        </div>
    </div>
</div>
