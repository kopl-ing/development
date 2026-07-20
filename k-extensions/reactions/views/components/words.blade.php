@php use Kopling\Reactions\Reaction; @endphp
@props(['data' => [], 'context' => null, 'oob' => false])
{{--
    Worded reactions ("Latest reactions"), merged into the rail's own row rather than a strip of
    their own -- registered `->after('kopling-reactions::rail')` (Extension::ux()), so its chips
    naturally land right after the rail's emoji buttons, before discussions' own `engage`/
    `quote-op` (both `ml-auto`, pinned to the row's end). `class="contents"` takes the wrapper
    out of layout entirely (see MDN's `display: contents`) so each chip becomes a direct flex
    item of Footer's own row instead of a block of its own -- the `id` stays addressable for
    htmx (the picker modal's own swap target, and `words-response.blade.php`'s/the toggle
    route's own OOB pair), just without imposing a wrapping box that used to force this onto its
    own line. Adding a reaction is done through the picker modal (opened by the rail's "+"),
    which posts to the word route and swaps this fragment; the wrapper stays rendered for a
    signed-in viewer even when empty so it's a valid htmx swap target.

    `$oob`: when the *toggle* route re-renders the rail (a plain click, or -- see below -- the
    viewer's own chip), it also carries this strip back as an out-of-band swap so a just-removed
    chip actually disappears without a page reload, the same reasoning `rail`'s own `$oob` prop
    already documents for the reverse direction (the word route carries the rail back).

    A reaction's own author gets the *whole chip* as a click-to-remove control -- a real
    `<button>`, not a `<span>`, posting to the same toggle route every plain rail pill already
    uses, with that reaction's own emoji. Deliberately the same interaction as an unworded
    reaction's rail pill (click to toggle it off), not a separate small affordance bolted onto
    the side of it -- the whole point is that removing a reaction works identically whether or
    not it carries a word, only the surface it's clicked from differs. The toggle route already
    finds and deletes by (reactable, person, emoji) regardless of word, so this reuses that exact
    delete path.
--}}
@php
    $reactable = $context?->getSubject();
    $actor = $context?->actor;
    $items = $reactable ? Reaction::latestWorded($reactable) : collect();
    $canReact = $actor !== null;
@endphp
@if ($reactable && ($items->isNotEmpty() || $canReact))
    <div id="rwords-{{ $reactable->id }}" @if ($oob) hx-swap-oob="true" @endif class="contents">
        @foreach ($items as $reaction)
            @php
                $name = $reaction->person?->name ?? __('kopling-reactions::messages.someone');
                $parts = preg_split('/\s+/', trim($name)) ?: [''];
                $initials = strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
                $hue = crc32((string) ($reaction->person?->id ?? $name)) % 360;
                $mine = $actor && $actor->id === $reaction->person_id;
            @endphp
            {{-- avatar circle · emoji · word (name on hover), matching the demo. Explicit
                 .kop-rchip classes (css/app.css) -- utility classes used only in an extension
                 view aren't in core's compiled stylesheet. --}}
            @if ($mine)
                <button type="button"
                        hx-post="{{ route('kopling-core::community/reactions.toggle', ['type' => $reactable->getMorphClass(), 'id' => $reactable->id]) }}"
                        hx-vals='{{ json_encode(['emoji' => $reaction->emoji], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) }}'
                        hx-target="#reactions-{{ $reactable->id }}"
                        hx-swap="outerHTML"
                        class="kop-rchip kop-rchip--mine shrink-0"
                        title="{{ __('kopling-reactions::messages.remove_reaction') }}"
                        aria-label="{{ __('kopling-reactions::messages.remove_reaction') }}">
                    <span class="kop-rchip__avatar" style="background:hsl({{ $hue }}deg 45% 45%)">{{ $initials }}</span>
                    <span class="kop-rchip__emoji" aria-hidden="true">{{ $reaction->emoji }}</span>
                    <span class="kop-rchip__word">{{ $reaction->word }}</span>
                </button>
            @else
                <span class="kop-rchip shrink-0" title="{{ $name }}">
                    <span class="kop-rchip__avatar" style="background:hsl({{ $hue }}deg 45% 45%)">{{ $initials }}</span>
                    <span class="kop-rchip__emoji" aria-hidden="true">{{ $reaction->emoji }}</span>
                    <span class="kop-rchip__word">{{ $reaction->word }}</span>
                </span>
            @endif
        @endforeach
    </div>
@endif
