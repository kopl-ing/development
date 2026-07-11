@php use Kopling\Discussions\Reply; @endphp
@props(['data' => [], 'context' => null])
{{--
    The activity teaser, in the card body after core's `content`: the demo's calm one-liner
    about the conversation ("N people used X words to talk about this"), linking to the
    discussion page. Reads `$context->subject` like every card leaf.
--}}
@php
    $moment = $context?->getSubject();
    $stats = $moment ? Reply::statsFor($moment) : null;
@endphp
@if ($moment && $stats)
    <a href="{{ route('discussions.show', $moment->id) }}"
       class="mt-1 block text-sm opacity-70 transition-opacity hover:opacity-100">
        @if ($stats['count'] === 0)
            {{ __('kopling-discussions::messages.teaser_empty') }}
        @else
            {{ trans_choice('kopling-discussions::messages.teaser', $stats['people'], [
                'people' => $stats['people'],
                'words' => number_format($stats['words']),
            ]) }}
        @endif
    </a>
@endif
