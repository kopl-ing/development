// Vanilla JS, event-delegated on `document` -- same shape as emoji-picker.js/tag-input.js. No
// lazy-loaded second module the way the emoji picker has: unlike emoji-mart's bundled dataset,
// there's no heavy payload to defer here -- search results are already server-rendered SVG
// strings (see Http\Controllers\IconSearchController), so this file never builds icon markup
// itself, only inserts what the server sent.

let openPicker = null;
let openContainer = null;
let debounceTimer = null;

function close() {
    openPicker?.remove();
    openPicker = null;
    openContainer = null;
    clearTimeout(debounceTimer);
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('mousedown', onClickOutside, true);
    window.removeEventListener('resize', close);
    document.removeEventListener('scroll', close, true);
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

function onClickOutside(event) {
    // Same reasoning as emoji-picker-mart.js's own onClickOutside: the popover lives outside
    // `container` in the DOM (appended to the closest <dialog> or document.body), so both need
    // checking, or a click on the popover itself would look "outside" and close it.
    if (openContainer && !openContainer.contains(event.target) && !openPicker?.contains(event.target)) {
        close();
    }
}

// Mirrors emoji-picker-mart.js's own position() exactly -- viewport-fixed coordinates, measured
// after the popover has actually rendered so its real size is known, so it floats free of any
// scrollable/height-constrained ancestor (daisyUI's .modal-box) instead of extending it.
function position(popover, trigger) {
    requestAnimationFrame(() => {
        const anchor = trigger.getBoundingClientRect();
        const size = popover.getBoundingClientRect();
        const margin = 8;

        const top = Math.min(anchor.bottom + 4, window.innerHeight - size.height - margin);
        const left = Math.min(anchor.left, window.innerWidth - size.width - margin);

        popover.style.top = `${Math.max(margin, top)}px`;
        popover.style.left = `${Math.max(margin, left)}px`;
        popover.style.visibility = 'visible';
    });
}

function select(container, icon) {
    const input = container.querySelector('[data-icon-input]');
    const display = container.querySelector('[data-icon-display]');
    const clearButton = container.querySelector('[data-icon-clear]');

    if (input) {
        input.value = icon.id;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (display) {
        display.innerHTML = icon.icon;
    }

    if (clearButton) {
        clearButton.hidden = false;
    }

    close();
}

function renderResults(list, container, icons, term) {
    list.innerHTML = '';

    if (icons.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'kop-icon-picker__empty';
        empty.textContent = term ? 'No icons found.' : 'Type to search icons…';
        list.appendChild(empty);

        return;
    }

    icons.forEach((icon) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'kop-icon-picker__option';
        button.title = icon.label;
        button.setAttribute('aria-label', icon.label);
        button.innerHTML = icon.icon;
        button.addEventListener('click', () => select(container, icon));
        list.appendChild(button);
    });
}

async function search(term, container, list) {
    const searchUrl = container.dataset.searchUrl;

    if (!term) {
        renderResults(list, container, [], term);

        return;
    }

    const response = await fetch(`${searchUrl}?q=${encodeURIComponent(term)}`);
    const icons = response.ok ? await response.json() : [];

    renderResults(list, container, icons, term);
}

/**
 * Opens the picker anchored to `container` (a `[data-kop-icon-picker]` mount), or closes it if
 * that same container's picker is already open -- one page-level "currently open picker" model,
 * same as emoji-picker-mart.js's own toggle(). Appended to the closest `<dialog>` (or
 * document.body), not to `container` itself, for the same reason position()'s docblock gives.
 */
export function toggle(container) {
    if (openContainer === container) {
        close();

        return;
    }

    close();

    const trigger = container.querySelector('[data-icon-trigger]');
    const host = container.closest('dialog') ?? document.body;

    const popover = document.createElement('div');
    popover.className = 'kop-icon-picker__popover';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'input input-sm w-full';
    input.placeholder = container.dataset.placeholder || 'Search…';
    input.setAttribute('aria-label', input.placeholder);

    const list = document.createElement('div');
    list.className = 'kop-icon-picker__results';

    popover.appendChild(input);
    popover.appendChild(list);
    host.appendChild(popover);

    renderResults(list, container, [], '');

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => search(input.value.trim(), container, list), 250);
    });

    input.focus();
    position(popover, trigger);

    openPicker = popover;
    openContainer = container;

    document.addEventListener('keydown', onKeydown);
    document.addEventListener('mousedown', onClickOutside, true);
    window.addEventListener('resize', close);
    document.addEventListener('scroll', close, true);
}

document.addEventListener('click', (event) => {
    const clearTrigger = event.target.closest('[data-icon-clear]');

    if (clearTrigger) {
        event.preventDefault();
        clearTrigger.hidden = true;

        const container = clearTrigger.closest('[data-kop-icon-picker]');
        const input = container?.querySelector('[data-icon-input]');
        const display = container?.querySelector('[data-icon-display]');

        if (input) {
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (display) {
            display.innerHTML = '＋';
        }

        return;
    }

    const openTrigger = event.target.closest('[data-icon-trigger]');

    if (!openTrigger) {
        return;
    }

    event.preventDefault();
    toggle(openTrigger.closest('[data-kop-icon-picker]'));
});
