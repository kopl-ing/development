@php
    // A single empty paragraph is TipTap's own minimal valid document -- what a brand new
    // compose form starts from when no $value was passed in.
    $initial = $value ?? '{"type":"doc","content":[{"type":"paragraph"}]}';
@endphp
{{-- Mount point for the editor.js bundle (see Ux/js/editor.js) -- vanilla JS, not an
     Alpine.data() component (core's Alpine.start() isn't guaranteed to run before/after an
     extension's own <script type="module">, see reply-dock's own note on this). The hidden
     input is what htmx's normal FormData submission already picks up, so composer/discussions/
     reply-dock's hx-post forms need no change beyond swapping in this component. --}}
<div data-tiptap-editor
     data-editor-name="{{ $name }}"
     data-editor-nodes="{{ json_encode($nodes) }}"
     @if ($placeholder) data-editor-placeholder="{{ $placeholder }}" @endif
     class="kop-editor">
    <div class="kop-editor__toolbar" data-editor-toolbar></div>
    <div class="kop-editor__content" data-editor-content></div>
    <input type="hidden" name="{{ $name }}" value="{{ $initial }}" data-editor-input>
</div>
