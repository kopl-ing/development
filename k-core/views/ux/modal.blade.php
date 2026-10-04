<button type="button" data-modal-show="{{ $id }}"
        {{ $attributes->merge(['class' => $trigger->attributes->get('class', 'btn btn-ghost btn-sm')]) }}
        aria-haspopup="dialog">
    {{ $trigger }}
</button>

{{-- grid-cols-1/grid-rows-1: daisyUI's implicit "auto" tracks collapse the box to its content, uncentered.
     No `.modal-backdrop` form on purpose: a mistap beside the box would throw away what was typed. --}}
<dialog id="{{ $id }}" class="modal grid-cols-1 grid-rows-1" aria-label="{{ $label }}">
    <div class="modal-box relative">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute end-2 top-2" aria-label="{{ __('kopling-core::ux.close') }}">
                <x-k::icon name="kopling-core::close" />
            </button>
        </form>
        {{ $slot }}
    </div>
</dialog>

{{-- Reopens after a failed validation when the form inside posts `_form` = this modal's id. --}}
@if (isset($errors) && $errors->any() && old('_form') === $id)
    <script>
        document.getElementById(@json($id))?.showModal();
    </script>
@endif
