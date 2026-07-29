@php use Kopling\Core\Ux\Context; @endphp
<div class="flex flex-row text-center items-center gap-2">
    <x-k::person.avatar :context="new Context(subject: $context->getSubject()?->person)" />
    <x-k::card.author :context="new Context(subject: $context->getSubject()?->person)" />
    <x-k::card.timestamp :context="$context" />
</div>