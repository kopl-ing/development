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
                    @php
                        $name = $reaction->person?->name ?? __('kopling-reactions::messages.someone');
                        $parts = preg_split('/\s+/', trim($name)) ?: [''];
                        $initials = strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
                        $hue = crc32((string) ($reaction->person?->id ?? $name)) % 360;
                    @endphp
                    {{-- avatar circle · emoji · word (name on hover), matching the demo --}}
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-base-200 ps-1 pe-3 py-1 text-sm" title="{{ $name }}">
                        <span class="w-6 h-6 shrink-0 rounded-full grid place-items-center text-[10px] font-bold leading-none text-white"
                              style="background:hsl({{ $hue }}deg 45% 45%)">{{ $initials }}</span>
                        <span aria-hidden="true" class="text-base leading-none">{{ $reaction->emoji }}</span>
                        <span class="opacity-90">{{ $reaction->word }}</span>
                    </span>
                @endforeach
            </div>
        @endif
    </div>
@endif
