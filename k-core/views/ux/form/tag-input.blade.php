{{--
    Mount point for tag-input.js (see its own docblock) -- vanilla JS (Tagify), not an
    Alpine.data() component, same ordering reasoning editor.js's own docblock gives. Two data-*
    hooks JS reads: `[data-tag-input-field]` is the real `<input>` Tagify mounts onto;
    `[data-tag-input-hidden]` is where JS maintains a plain `name="{name}[]"` hidden input per
    selected tag -- Tagify's own native behaviour is to serialize the whole selection as one
    JSON string back into its underlying input, which doesn't match the plain PHP array
    (`request()->input($name, [])`) every server-side consumer already expects, so JS keeps
    these hidden inputs in sync instead of relying on Tagify's own serialization.
--}}
<fieldset class="fieldset">
    <legend class="fieldset-legend">{{ $label }}</legend>

    <div data-tag-input
         data-search-url="{{ $searchUrl }}"
         data-name="{{ $name }}"
         @if ($max !== null) data-max="{{ $max }}" @endif
         data-initial-value="{{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}">
        <input type="text" data-tag-input-field
               placeholder="{{ $placeholder ?? __('kopling-core::ux.search_placeholder') }}">
        <div data-tag-input-hidden></div>
    </div>

    @if ($min !== null && $max !== null)
        <p class="label text-xs opacity-60">{{ __('kopling-core::ux.select_min_max', ['min' => $min, 'max' => $max]) }}</p>
    @elseif ($min !== null)
        <p class="label text-xs opacity-60">{{ __('kopling-core::ux.select_min', ['min' => $min]) }}</p>
    @elseif ($max !== null)
        <p class="label text-xs opacity-60">{{ __('kopling-core::ux.select_max', ['max' => $max]) }}</p>
    @endif
    @if ($description)
        <p class="label">{{ $description }}</p>
    @endif
</fieldset>
