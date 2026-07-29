// Thin, always-loaded shim -- mirrors editor.js's own dynamic-import split (see its docblock
// for the general shape). The real emoji-mart payload (emoji-picker-mart.js, ~100KB+ gzipped
// once its data file is included) is only ever import()ed the first time a [data-emoji-trigger]
// is actually clicked -- lazier than the editor, which must mount eagerly since ProseMirror is
// a live content-editable surface. A [data-kop-emoji-picker] that nobody ever opens (most of
// them, most of the time -- e.g. a tag admin row nobody is currently editing) never pays for it.
//
// Event delegation on `document` means there's no mount/unmount bookkeeping across htmx swaps
// to maintain either -- unlike the editor, this widget holds no persistent instance state
// between opens, so a swapped-in trigger just works the next time it's clicked.
let modulePromise = null;

function loadPicker() {
    if (!modulePromise) {
        modulePromise = import('./emoji-picker-mart.js');
    }

    return modulePromise;
}

function clearField(container) {
    const input = container.querySelector('[data-emoji-input]');
    const display = container.querySelector('[data-emoji-display]');

    if (input) {
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (display) {
        display.textContent = '＋';
    }
}

document.addEventListener('click', async (event) => {
    const clearTrigger = event.target.closest('[data-emoji-clear]');

    if (clearTrigger) {
        event.preventDefault();
        clearTrigger.hidden = true;
        clearField(clearTrigger.closest('[data-kop-emoji-picker]'));

        return;
    }

    const openTrigger = event.target.closest('[data-emoji-trigger]');

    if (!openTrigger) {
        return;
    }

    event.preventDefault();

    const container = openTrigger.closest('[data-kop-emoji-picker]');
    const { toggle } = await loadPicker();

    toggle(container);
});
