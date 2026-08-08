@if ($flaggable)
    {{-- A plain divider ahead of the moderator-only cluster (Hide/Delete/Pin) -- a visual break
         from Report just above it, the one action any signed-in person can take, not just a
         moderator. A bare <hr> rather than a border on the trigger button itself: the button's
         own rounded corners made a border-t there read as a boxed-in outline, not a clean line. --}}
    <hr class="border-base-300 my-1">
    <x-k::modal :label="__('kopling-moderation::moderation.hide')" class="w-full justify-start">
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
