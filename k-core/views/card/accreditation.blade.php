@php use Kopling\Core\Ux\Context; @endphp
<div class="flex flex-row text-center items-center gap-2">
    <x-k::person.avatar :context="$context" />
    <x-k::card.author :context="$context" />
    <x-k::card.timestamp :context="$context" />
</div>