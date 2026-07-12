@php use Kopling\Core\Ux\Context; @endphp
{{--
    The tag page: everything under one tag. Reuses the base portal shell (`<x-k::portal.
    layout>`) and core's own card component, so a card here renders through the exact same
    Top/Body/Footer extensibility (tags, reactions, ...) as one in the feed -- and inherits
    the active theme -- without duplicating any card markup or coupling to the portal feed.
--}}
<x-k::community.chrome>
    <div class="mx-auto flex max-w-2xl flex-col gap-4 p-6">
        <div>
            <a href="/" class="btn btn-ghost btn-sm">&larr; {{ __('kopling-tags::messages.back') }}</a>
        </div>

        <h1 class="flex items-center gap-2 text-2xl font-bold">
            <span class="badge badge-lg"
                  @if ($tag->color) style="background-color:{{ $tag->color }};border-color:{{ $tag->color }};color:#fff" @endif>
                {{ $tag->name }}
            </span>
        </h1>

        @forelse ($moments as $moment)
            <x-k::card.card :context="new Context(subject: $moment)" />
        @empty
            <p class="opacity-70">{{ __('kopling-tags::messages.empty') }}</p>
        @endforelse
    </div>
</x-k::community.chrome>
