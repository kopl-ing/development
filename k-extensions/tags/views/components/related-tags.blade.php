@php
    use Kopling\Core\Ux\Form\IconSearch\IconRenderer;
    use Kopling\Tags\Tag;
@endphp
@props(['data' => [], 'context' => null])
{{--
    The current moment's own tags, each with its icon/name/description/latest activity -- so a
    reader arriving on one moment (the most common first visit, not the homepage) can see what
    else they could talk about, right where they're already reading. Renders nothing outside a
    moment detail page ($context->isRoute() safely returns false rather than throwing when
    there's no bound subject at all -- see Context's own docblock) and nothing if this moment
    carries no tags.
--}}
@php
    $moment = $context?->isRoute('moment') ? $context->getSubject() : null;
    $tags = $moment ? Tag::forMoment($moment) : collect();
@endphp
@if ($moment && $tags->isNotEmpty())
    <div class="card bg-base-100 border border-base-300 rounded-box mb-4">
        <div class="card-body p-4 gap-3">
            <h3 class="text-xs font-bold uppercase tracking-wide opacity-60">{{ __('kopling-tags::messages.related_tags') }}</h3>
            <ul class="flex flex-col gap-3">
                @foreach ($tags as $tag)
                    <li>
                        <a href="{{ route('kopling-core::community/tags.show', $tag->slug) }}"
                           class="flex items-start gap-2 no-underline hover:opacity-80">
                            @if ($tag->icon)
                                <span class="mt-0.5 shrink-0">{!! IconRenderer::svg($tag->icon, '1em', $tag->color) !!}</span>
                            @endif
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold">{{ $tag->name }}</span>
                                @if ($tag->description)
                                    <span class="text-xs opacity-70">{{ $tag->description }}</span>
                                @endif
                                @if ($tag->latestActivity())
                                    <span class="text-xs opacity-50">{{ $tag->latestActivity()->diffForHumans() }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
