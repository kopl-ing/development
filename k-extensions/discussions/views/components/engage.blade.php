@php use Kopling\Discussions\Reply; @endphp
@props(['data' => [], 'context' => null])
{{--
    The engage bar, in the card footer: a single Reply / discussion link, labelled with the
    reply count. Registered after the reactions rail so, with both installed, reactions read
    first and this closes the footer.
--}}
@php
    $moment = $context?->getSubject();
    $count = $moment ? Reply::statsFor($moment)['count'] : 0;
@endphp
@if ($moment)
    <a href="{{ $context->getSubjectUrl() }}" class="btn btn-sm btn-ghost gap-1">
        <x-k::icon name="kopling-discussions::comment" class="w-4 h-4" />
        {{ trans_choice('kopling-discussions::messages.replies', $count, ['count' => $count]) }}
    </a>
@endif
