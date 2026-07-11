@php use Kopling\Tags\Tag; @endphp
@props(['data' => [], 'context' => null])
{{--
    A moment's tags as badge links, rendered at the top of the card body (before core's
    `content`). Reads `$context->subject` (the Moment) like every card leaf. Each badge links
    to the tag's own page. A tag's optional `color` is a per-tag brand choice independent of
    the daisyUI theme, so it's applied as an inline style (the one sanctioned exception to
    "semantic colours only"); untinted tags fall back to daisyUI's own `badge`.
--}}
@php
    $moment = $context?->getSubject();
    $tags = $moment ? Tag::forMoment($moment) : collect();
@endphp
@if ($tags->isNotEmpty())
    <div class="mb-1 flex flex-wrap items-center gap-1.5">
        @foreach ($tags as $tag)
            <a href="{{ route('tags.show', $tag->slug) }}"
               class="badge badge-sm no-underline"
               @if ($tag->color) style="background-color:{{ $tag->color }};border-color:{{ $tag->color }};color:#fff" @endif
               title="{{ __('kopling-tags::messages.browse', ['name' => $tag->name]) }}">
                {{ $tag->name }}
            </a>
        @endforeach
    </div>
@endif
