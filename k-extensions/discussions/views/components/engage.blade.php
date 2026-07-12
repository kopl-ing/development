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
    <a href="{{ route('kopling-core::community/discussions.show', $moment->id) }}" class="btn btn-sm btn-ghost gap-1">
        <span aria-hidden="true">💬</span>
        {{ trans_choice('kopling-discussions::messages.replies', $count, ['count' => $count]) }}
    </a>
@endif
