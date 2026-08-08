@if ($flaggable)
    <x-k::modal :label="__('kopling-moderation::moderation.hide')">
        <x-slot:trigger><span class="text-error">{{ __('kopling-moderation::moderation.hide') }}</span></x-slot:trigger>
        {{-- The card itself is the swap target -- once hidden there's no new state left to
             render in its place (see HideControlEntry's own docblock), so the whole card
             (dialog included) simply disappears on a successful htmx response. --}}
        <form method="POST" action="{{ route('kopling-core::community/flag.hide', ['type' => $type, 'id' => $flaggable->id]) }}"
              hx-post="{{ route('kopling-core::community/flag.hide', ['type' => $type, 'id' => $flaggable->id]) }}"
              hx-target="closest .card" hx-swap="outerHTML"
              class="flex flex-col gap-4">
            @csrf
            <x-k::form.text-area :data="[
                'name' => 'reason',
                'label' => __('kopling-moderation::moderation.hide_reason'),
            ]" />
            <button type="submit" class="btn btn-error self-start">{{ __('kopling-moderation::moderation.confirm_hide') }}</button>
        </form>
    </x-k::modal>
@endif
