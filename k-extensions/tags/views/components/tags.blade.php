@php
    use Kopling\Core\Ux\Form\IconSearch\IconRenderer;
    use Kopling\Tags\Tag;
@endphp
@props(['data' => [], 'context' => null])
{{--
    A moment's tags as badge links, floating on the card's own top edge, above the title
    (`Card\Badges::SLOT` -- see that component's own docblock for the positioning mechanics)
    rather than inside the body itself. Reads `$context->subject` (the Moment) like every card
    leaf. Each badge links
    to the tag's own page, with its icon before its name. A tag's optional `color` is a per-tag
    brand choice independent of the daisyUI theme, so it's applied as an inline style (the one
    sanctioned exception to "semantic colours only") -- the icon deliberately isn't tinted to
    that same color: it already sits on that exact color as the badge's own background (text
    set to white alongside it), so it just inherits that via `currentColor` rather than being
    tinted to match its own backdrop (which would render it invisible). Untinted tags fall back
    to daisyUI's own `badge` colors for both.

    Suppressed entirely when the moment carries only this one tag *and* the current page is that
    same tag's own show page (`request()->route('slug')`, the `/tag/{slug}` route's own plain
    string param) -- a lone pill for the exact tag the whole page is already about is pure
    repetition. A second tag alongside it, or a different page entirely, still renders normally.
--}}
@php
    $moment = $context?->getSubject();
    $tags = $moment ? Tag::forMoment($moment) : collect();

    $currentTagSlug = request()->route('slug');

    if ($currentTagSlug !== null && $tags->count() === 1 && $tags->first()->slug === $currentTagSlug) {
        $tags = collect();
    }
@endphp
@if ($tags->isNotEmpty())
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach ($tags as $tag)
            <a href="{{ route('kopling-core::community/tags.show', $tag->slug) }}"
               class="badge badge-sm gap-1 no-underline"
               @if ($tag->color) style="background-color:{{ $tag->color }};border-color:{{ $tag->color }};color:#fff" @endif
               title="{{ __('kopling-tags::messages.browse', ['name' => $tag->name]) }}">
                @if ($tag->icon)
                    {!! IconRenderer::svg($tag->icon, '0.9em') !!}
                @endif
                {{ $tag->name }}
            </a>
        @endforeach
    </div>
@endif
