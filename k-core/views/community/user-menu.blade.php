@php use Kopling\Core\Ux\Context; @endphp
@if ($context->actor)
    @if ($entries->isNotEmpty())
        <x-k::dropdown :label="__('kopling-core::community.account_menu')" align="dropdown-end">
            <x-slot:trigger>
                <x-k::card.avatar :context="new Context(subject: $context->getActor())" />
            </x-slot:trigger>
            @foreach ($entries as $entry)
                <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
            @endforeach
        </x-k::dropdown>
    @else
        <x-k::card.avatar :context="new Context(subject: $context->getActor())" />
    @endif
@endif
