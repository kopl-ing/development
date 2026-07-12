@php use Kopling\Reactions\Reaction; @endphp
@props(['data' => [], 'context' => null])
{{--
    The "Latest reactions" strip -- recent worded reactions on a moment, newest first.
    Registered into `core::card.footer` after the rail. Adding a reaction is done through the
    picker modal (opened by the rail's "+"), which posts to the word route and swaps this
    strip; the container stays rendered for a signed-in viewer even when empty so it's a valid
    htmx swap target.
--}}
@php
    $moment = $context?->getSubject();
    $actor = $context?->actor;
    $items = $moment ? Reaction::latestWorded($moment) : collect();
    $canReact = $actor !== null;
@endphp
@if ($moment && ($items->isNotEmpty() || $canReact))
    <div id="rwords-{{ $moment->id }}" class="mt-1 flex w-full flex-col gap-1.5">
        @if ($items->isNotEmpty())
            <div class="text-xs font-semibold uppercase tracking-wide opacity-60">
                {{ __('kopling-reactions::messages.latest') }}
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach ($items as $reaction)
                    <span class="badge badge-ghost h-auto gap-1 py-1">
                        <span class="font-semibold">{{ $reaction->person?->name ?? __('kopling-reactions::messages.someone') }}</span>
                        <span>{{ $reaction->word }}</span>
                        <span aria-hidden="true">{{ $reaction->emoji }}</span>
                    </span>
                @endforeach
            </div>
        @endif
    </div>
@endif
