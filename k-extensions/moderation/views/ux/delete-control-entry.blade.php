@if ($flaggable)
    <x-k::modal :label="__('kopling-moderation::moderation.delete')">
        <x-slot:trigger><span class="text-error">{{ __('kopling-moderation::moderation.delete') }}</span></x-slot:trigger>
        {{-- Same swap target/reasoning as hide-control-entry.blade.php -- the card disappears
             on a successful response, there's no "deleted" state left to render in its place. --}}
        <form method="POST" action="{{ route('kopling-core::community/flag.destroy', ['type' => $type, 'id' => $flaggable->id]) }}"
              hx-post="{{ route('kopling-core::community/flag.destroy', ['type' => $type, 'id' => $flaggable->id]) }}"
              hx-target="closest .card" hx-swap="outerHTML"
              class="flex flex-col gap-4">
            @csrf
            <p class="text-sm opacity-70">{{ __('kopling-moderation::moderation.delete_warning') }}</p>
            <x-k::form.text-area :data="[
                'name' => 'reason',
                'label' => __('kopling-moderation::moderation.delete_reason'),
            ]" />
            <button type="submit" class="btn btn-error self-start">{{ __('kopling-moderation::moderation.confirm_delete') }}</button>
        </form>
    </x-k::modal>
@endif
