@props(['data' => [], 'context' => null])
@php
    // A persisted Moment means this render is the edit form, not a fresh compose box -- Cancel
    // has to discard against the server (there's a real previous state to revert to) instead of
    // just collapsing back to an empty box, and the primary action reads "Save", not "Post".
    $moment = $context?->getSubject();
    $editing = $moment?->exists ?? false;
@endphp
<div x-show="open" x-cloak class="flex items-center justify-end gap-2 ml-auto">
    @if ($editing)
        <button type="button"
                hx-get="{{ route('kopling-core::community/compose.show', $moment) }}"
                hx-target="closest .card" hx-swap="outerHTML"
                class="btn btn-ghost btn-sm">{{ __('kopling-composer::messages.cancel') }}</button>
        <button type="submit" class="btn btn-primary btn-sm">
            {{ __('kopling-composer::messages.save') }}
        </button>
    @else
        <button type="button" @click="open = false; reset(); $refs.form.reset()"
                class="btn btn-ghost btn-sm">{{ __('kopling-composer::messages.cancel') }}</button>
        <button type="submit" class="btn btn-primary btn-sm">
            {{ __('kopling-composer::messages.post') }}
        </button>
    @endif
</div>
