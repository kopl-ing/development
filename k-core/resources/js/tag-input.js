// Thin, always-loaded shim -- mirrors editor.js's own dynamic-import split (see its docblock).
// The real Tagify payload (tag-input-tagify.js) is only import()ed once a page actually has a
// tag-input mount point, so pages without one never load it. Mounts eagerly (not on a click,
// unlike emoji-picker.js) -- a tag input needs to be visible and interactive the moment the
// page loads, same as the editor, not hidden behind a deliberate open action.
const instances = new WeakMap();

function unmounted() {
    return document.querySelectorAll('[data-tag-input]:not([data-tag-input-mounted])');
}

async function mountAll() {
    const nodes = unmounted();

    if (nodes.length === 0) {
        return;
    }

    const { mount } = await import('./tag-input-tagify.js');

    nodes.forEach((node) => {
        if (node.hasAttribute('data-tag-input-mounted')) {
            return;
        }

        node.setAttribute('data-tag-input-mounted', '');
        instances.set(node, mount(node));
    });
}

function unmount(node) {
    const instance = instances.get(node);

    if (instance) {
        instance.destroy();
        instances.delete(node);
    }

    node.removeAttribute('data-tag-input-mounted');
}

document.addEventListener('DOMContentLoaded', mountAll);

// Same htmx-swap teardown/remount reasoning editor.js already documents -- a posted moment
// prepends new markup elsewhere on the page, and the compose card itself can be replaced.
document.body.addEventListener('htmx:before:swap', (event) => {
    const target = event.detail?.ctx?.target;

    if (!target) {
        return;
    }

    if (target.matches?.('[data-tag-input]')) {
        unmount(target);
    }

    target.querySelectorAll?.('[data-tag-input]').forEach(unmount);
});

document.body.addEventListener('htmx:after:swap', mountAll);
