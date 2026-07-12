@php use Kopling\Reactions\Reaction; @endphp
@props(['data' => [], 'context' => null])
{{--
    The "Latest reactions" strip -- recent worded reactions on a moment, newest first, plus
    an inline form for the viewer to add their own (emoji + a short word). Registered into
    `core::card.footer` after the rail. Same context-reading + anonymous-component + htmx
    conventions as the rail; the form's response swaps this strip and carries the rail back
    out-of-band so its counts stay current.
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

        @if ($canReact)
            <form hx-post="{{ route('kopling-core::community/reactions.word', $moment->id) }}"
                  hx-target="#rwords-{{ $moment->id }}"
                  hx-swap="outerHTML"
                  class="flex items-center gap-1.5">
                <select name="emoji" class="select select-xs w-16" aria-label="{{ __('kopling-reactions::messages.emoji') }}">
                    @foreach (Reaction::PALETTE as $emoji)
                        <option value="{{ $emoji }}">{{ $emoji }}</option>
                    @endforeach
                </select>
                <input type="text" name="word" required
                       maxlength="{{ Reaction::WORD_MAX }}"
                       placeholder="{{ __('kopling-reactions::messages.word_placeholder') }}"
                       class="input input-xs min-w-0 flex-1" />
                <button type="submit" class="btn btn-xs btn-primary">
                    {{ __('kopling-reactions::messages.word_submit') }}
                </button>
            </form>
        @endif
    </div>
@endif
