{{-- Not a `formmethod="dialog"` submit: htmx boost ignores the submitter's formmethod and sends a request instead. --}}
<button type="button" data-modal-close {{ $attributes->merge(['class' => 'btn btn-ghost']) }}>
    {{ __('kopling-core::ux.cancel') }}
</button>
