// Vanilla JS, event-delegated on `document`, same shape as emoji-picker.js/tag-input.js -- the
// search input/results are wired declaratively via htmx instead, so this file only
// positions/opens/closes the popover and reads the selection back out of it.

let openPicker = null;
let openContainer = null;

function close() {
    openPicker?.remove();
    openPicker = null;
    openContainer = null;
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

function select(container, id, icon) {
    const input = container.querySelector('[data-icon-input]');
    const display = container.querySelector('[data-icon-display]');
    const clearButton = container.querySelector('[data-icon-clear]');

    if (input) {
        input.value = id;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (display) {
        display.innerHTML = icon;
    }

    if (clearButton) {
        clearButton.hidden = false;
    }

    close();
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
    const searchUrl = container.dataset.searchUrl;

    const popover = document.createElement('div');
    popover.className = 'kop-icon-picker__popover';

    const list = document.createElement('div');
    list.className = 'kop-icon-picker__results';
    list.id = `kop-icon-results-${Math.random().toString(36).slice(2)}`;

    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'q';
    input.className = 'input input-sm w-full';
    input.placeholder = container.dataset.placeholder || 'Search…';
    input.setAttribute('aria-label', input.placeholder);
    input.setAttribute('hx-get', searchUrl);
    input.setAttribute('hx-trigger', 'input changed delay:250ms, load');
    input.setAttribute('hx-target', `#${list.id}`);
    input.setAttribute('hx-swap', 'innerHTML');

    popover.appendChild(input);
    popover.appendChild(list);
    host.appendChild(popover);

    window.htmx.process(popover);

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
    const optionButton = event.target.closest('[data-icon-option]');

    if (optionButton && openContainer) {
        event.preventDefault();
        select(openContainer, optionButton.dataset.iconId, optionButton.innerHTML);

        return;
    }

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
