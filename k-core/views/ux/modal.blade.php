<button type="button" data-modal-show="{{ $id }}"
        {{ $attributes->merge(['class' => $trigger->attributes->get('class', 'btn btn-ghost btn-sm')]) }}
        aria-haspopup="dialog">
    {{ $trigger }}
</button>

{{-- grid-cols-1/grid-rows-1: daisyUI's implicit "auto" tracks collapse the box to its content, uncentered. --}}
<dialog id="{{ $id }}" class="modal grid-cols-1 grid-rows-1" aria-label="{{ $label }}">
    <div class="modal-box">
        {{ $slot }}
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>{{ __('kopling-core::ux.close') }}</button>
    </form>
</dialog>

{{-- Reopens after a failed validation when the form inside posts `_form` = this modal's id. --}}
@if (isset($errors) && $errors->any() && old('_form') === $id)
    <script>
        document.getElementById(@json($id))?.showModal();
    </script>
@endif
