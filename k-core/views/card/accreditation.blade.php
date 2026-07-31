{{-- `Context` requires a real subject or a query -- skip Avatar/Author for a linkable model with no `->person` rather than passing it a null one. --}}
@php use Kopling\Core\Ux\Context; $person = $context->getSubject()?->person; @endphp
<div class="flex flex-row text-center items-center gap-2">
    @if ($person)
        <x-k::person.avatar :context="new Context(subject: $person)" />
        <x-k::card.author :context="new Context(subject: $person)" />
    @endif
    <x-k::card.timestamp :context="$context" />
</div>