@props(['data' => [], 'context' => null])
<div x-show="open" x-cloak class="flex items-center justify-end gap-2 ml-auto">
    <button type="button" @click="open = false; reset(); $refs.form.reset()"
            class="btn btn-ghost btn-sm">{{ __('kopling-composer::messages.cancel') }}</button>
    <button type="submit" class="btn btn-primary btn-sm">
        {{ __('kopling-composer::messages.post') }}
    </button>
</div>
