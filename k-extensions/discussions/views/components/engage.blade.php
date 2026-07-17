@php use Kopling\Discussions\Reply; @endphp
@props(['data' => [], 'context' => null])
{{--
    The engage bar, in the card footer: a single Reply / discussion link, labelled with the
    reply count. Registered after the reactions rail so, with both installed, reactions read
    first and this closes the footer. Skipped on the moment's own discussion page -- the reply
    thread is already right there below the card, so a "N replies" link back to the very page
    you're on is dead weight, not a shortcut.
--}}
@php
    $moment = $context?->getSubject();
    $count = $moment ? Reply::statsFor($moment)['count'] : 0;
@endphp
@if ($moment && ! $context->isRoute('moment'))
    <a href="{{ $context->getSubjectUrl() }}" class="btn btn-sm btn-ghost gap-1">
        <x-k::icon name="kopling-discussions::comment" class="w-4 h-4" />
        {{ trans_choice('kopling-discussions::messages.replies', $count, ['count' => $count]) }}
    </a>
@endif
