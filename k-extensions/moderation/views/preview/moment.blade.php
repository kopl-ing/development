@php
    use Kopling\Core\Ux\Context;
@endphp
{{-- The real Moment card -- same `Card\Card` the community feed itself renders, not a bespoke
     summary that can drift out of sync with it. `control-slot` points at an unregistered slot,
     deliberately suppressing the card's own "..." action menu: HideControlEntry/DeleteControlEntry
     are built assuming a non-trashed subject reachable through the normal feed (see
     HideControlEntry's own docblock), which doesn't hold for an already-hidden flaggable shown
     here. The queue row's own action buttons below already handle Hide/Unhide/Delete correctly
     for both states, so they stay the only controls on this card. --}}
<x-k::card.card :context="new Context(subject: $flaggable)" control-slot="kopling-moderation::queue-preview" />
