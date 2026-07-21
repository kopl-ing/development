@props(['data' => [], 'context' => null])
@php
    $moment = $context?->getSubject();
    $poll = $moment?->poll;
    $person = auth()->user();
@endphp
@if ($poll && $poll->isVisibleTo($person))
    @php
        $showResults = $poll->resultsVisibleTo($person);
        $total = $poll->votes->count();
    @endphp
    <div id="poll-{{ $poll->id }}">
        <p class="font-medium mb-2">{{ $poll->question }}</p>

        @if ($showResults)
            <div class="flex flex-col gap-2">
                @foreach ($poll->options as $option)
                    @php
                        $count = $option->votes->count();
                        $pct = $total > 0 ? round($count / $total * 100) : 0;
                        $mine = $person && $option->votes->contains('person_id', $person->id);
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span @class(['font-semibold' => $mine])>{{ $option->display() }}</span>
                            <span class="opacity-70">{{ $pct }}% ({{ $count }})</span>
                        </div>
                        <progress @class(['progress', 'w-full', 'progress-primary' => $mine, 'progress-neutral' => ! $mine])
                                  value="{{ $pct }}" max="100"></progress>
                    </div>
                @endforeach
            </div>
        @endif

        @if (! $poll->isClosed() && $person)
            <form method="POST" action="{{ route('kopling-core::community/poll.vote', $poll) }}"
                  hx-post="{{ route('kopling-core::community/poll.vote', $poll) }}"
                  hx-target="#poll-{{ $poll->id }}" hx-swap="outerHTML"
                  class="flex flex-col gap-2 mt-3">
                @csrf
                @foreach ($poll->options as $option)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="{{ $poll->multiple_choice ? 'checkbox' : 'radio' }}" name="option_ids[]" value="{{ $option->id }}"
                               class="{{ $poll->multiple_choice ? 'checkbox checkbox-sm' : 'radio radio-sm' }}">
                        <span>{{ $option->display() }}</span>
                    </label>
                @endforeach
                <button type="submit" class="btn btn-primary btn-sm self-start">
                    {{ $poll->hasVoted($person) ? __('kopling-poll::messages.revote') : __('kopling-poll::messages.vote') }}
                </button>
            </form>
        @endif

        @if ($poll->isClosed())
            <p class="text-xs opacity-60 mt-2">{{ __('kopling-poll::messages.closed') }}</p>
        @endif
    </div>
@endif
