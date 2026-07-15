<button type="button" data-modal-show="{{ $id }}"
        {{ $attributes->merge(['class' => 'btn btn-ghost btn-sm']) }}
        aria-haspopup="dialog">
    {{ $trigger }}
</button>

<dialog id="{{ $id }}" class="modal" aria-label="{{ $label }}">
    <div class="modal-box">
        {{ $slot }}
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>{{ __('kopling-core::ux.close') }}</button>
    </form>
</dialog>
