<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>
    {{--
        Mount point for icon-picker.js -- vanilla JS, event-delegated on `document`, same shape
        as emoji-picker.js's own mount. Unlike the emoji picker, there's no heavy bundled dataset
        to lazy-load behind a dynamic import(): search results come from the server
        (Http\Controllers\IconSearchController), already rendered to SVG, so this widget's own
        JS only ever renders strings the server produced.
    --}}
    <div class="kop-icon-picker" data-kop-icon-picker data-search-url="{{ $searchUrl }}"
         @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif>
        <button type="button" class="btn" data-icon-trigger aria-haspopup="true" aria-label="{{ $label }}">
            <span data-icon-display class="kop-icon-picker__display">{!! $icon ?? '＋' !!}</span>
        </button>
        <button type="button" class="btn btn-ghost btn-xs" data-icon-clear
                @if (! $value) hidden @endif
                aria-label="{{ __('kopling-core::ux.clear') }}">✕</button>
        <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-icon-input>
    </div>
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
