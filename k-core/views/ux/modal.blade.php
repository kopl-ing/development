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

{{--
    Self-reopens after a validation-error redirect-back: a caller whose own `<form>` inside the
    slot above includes a hidden `<input type="hidden" name="_form" value="{{ $id }}">` gets
    this dialog automatically reshown once `old('_form')` comes back matching this exact modal's
    own id -- no page-level script or state needed from the caller. A form that never adds that
    hidden input is entirely unaffected (`old('_form')` simply never equals this modal's `$id`),
    so this is a no-op for every other existing caller of this component.
--}}
@if (isset($errors) && $errors->any() && old('_form') === $id)
    <script>
        document.getElementById(@json($id))?.showModal();
    </script>
@endif
