<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>
    {{--
        Mount point for emoji-picker.js (see its own docblock) -- vanilla JS, not an
        Alpine.data() component, same ordering reasoning editor.js already documents. The
        hidden input is what a plain <form> POST or htmx's FormData submission already picks
        up; the trigger/clear buttons are plain data-attribute targets, not passed any PHP
        state directly, so the JS shim never needs to know this component's own markup shape
        beyond the four data-* hooks below.
    --}}
    <div class="kop-emoji-picker" data-kop-emoji-picker>
        <button type="button" class="btn" data-emoji-trigger aria-haspopup="true" aria-label="{{ $label }}">
            <span data-emoji-display class="kop-emoji-picker__display">{{ $value ?: '＋' }}</span>
        </button>
        <button type="button" class="btn btn-ghost btn-xs" data-emoji-clear
                @if (! $value) hidden @endif
                aria-label="{{ __('kopling-core::ux.clear') }}">✕</button>
        <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-emoji-input>
    </div>
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
