// The real emoji-mart payload -- dynamically import()ed by emoji-picker.js, never loaded on a
// page whose [data-emoji-trigger] buttons are never clicked. `emoji-mart`/`@emoji-mart/data`
// (MIT, https://github.com/missive/emoji-mart) render into their own shadow DOM, so none of
// their internal styling leaks into daisyUI/Tailwind -- only the popover's own positioning is
// this codebase's concern (see emoji-picker.css).
import { Picker } from 'emoji-mart';
import data from '@emoji-mart/data';

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
    // The popover no longer lives inside `openContainer` (see `toggle()`'s own docblock), so
    // both need checking -- otherwise a click on the picker itself would look "outside" and
    // close it before the emoji-mart's own onEmojiSelect ever fires.
    if (openContainer && !openContainer.contains(event.target) && !openPicker?.contains(event.target)) {
        close();
    }
}

/**
 * Positions the popover with viewport (`position: fixed`) coordinates rather than anchoring it
 * inside `container`'s own layout -- `container` may sit inside a scrollable, height-
 * constrained ancestor (daisyUI's `.modal-box`, `overflow-y: auto`), where a plain
 * `position: absolute` popover would just extend that ancestor's own scrollable area instead
 * of floating free above it. `openHost` (the closest `<dialog>`, or `document.body` outside
 * one) is never that scrollable ancestor -- a native `<dialog>` itself is `position: fixed;
 * inset: 0` (see daisyUI's `.modal`), so it never clips, and appending there keeps the popover
 * inside the dialog's own top-layer subtree, still painting above its backdrop.
 *
 * Measured after appending (not computed up front) because emoji-mart's own custom element
 * only reports its real size once it has actually rendered.
 */
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

function select(container, emoji) {
    const input = container.querySelector('[data-emoji-input]');
    const display = container.querySelector('[data-emoji-display]');
    const clearButton = container.querySelector('[data-emoji-clear]');

    if (input) {
        input.value = emoji.native;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (display) {
        display.textContent = emoji.native;
    }

    if (clearButton) {
        clearButton.hidden = false;
    }

    close();
}

/**
 * Opens the picker anchored to `container` (a `[data-kop-emoji-picker]` mount), or closes it
 * if that same container's picker is already open -- a single page-level "currently open
 * picker" model, same as the reactions extension's own single picker modal. Appended to
 * `container`'s closest `<dialog>` (or `document.body` when there isn't one), not to
 * `container` itself -- see `position()`'s own docblock for why.
 */
export function toggle(container) {
    if (openContainer === container) {
        close();

        return;
    }

    close();

    const trigger = container.querySelector('[data-emoji-trigger]');
    const host = container.closest('dialog') ?? document.body;

    const picker = new Picker({
        data,
        theme: 'auto',
        previewPosition: 'none',
        onEmojiSelect: (emoji) => select(container, emoji),
    });

    picker.classList.add('kop-emoji-picker__popover');
    host.appendChild(picker);
    position(picker, trigger);

    openPicker = picker;
    openContainer = container;

    document.addEventListener('keydown', onKeydown);
    // Capture phase so this runs before the click-delegation listener in emoji-picker.js would
    // otherwise re-open the same picker it just closed when the trigger itself is clicked again.
    document.addEventListener('mousedown', onClickOutside, true);
    window.addEventListener('resize', close);
    // Non-bubbling, so only a capture-phase listener on an ancestor ever sees a scroll fired on
    // a descendant like `.modal-box` -- closing (rather than repositioning) on scroll keeps this
    // simple, matching the same "close, don't chase" behaviour Escape/outside-click already use.
    document.addEventListener('scroll', close, true);
}
