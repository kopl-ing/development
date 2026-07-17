// Thin, always-loaded shim -- the real TipTap/ProseMirror payload (editor-tiptap.js) is
// dynamically import()ed only once a page actually has an editor mount point, so pages without
// one (most of the site, most of the time) never pay for it. First use of dynamic import() in
// this codebase -- worth knowing when reviewing bundle-size impact.
//
// Deliberately not an Alpine.data() component: core's own Alpine.start() runs as part of this
// same app.js/editor.js pairing, but there's no guaranteed load order between this file and
// whatever Portal-attached extension <script type="module"> tags might also be on the page --
// the same reason reply-dock already sticks to inline x-data + plain window events instead of
// Alpine.data()/stores (see its own dock.blade.php note). Editor instances instead expose a
// small imperative API directly on their own mount element (`node.kopEditor`), and lifecycle is
// driven by plain htmx events.
const instances = new WeakMap();

function unmounted() {
    return document.querySelectorAll('[data-tiptap-editor]:not([data-tiptap-mounted])');
}

async function mountAll() {
    const nodes = unmounted();

    if (nodes.length === 0) {
        return;
    }

    const { mount } = await import('./editor-tiptap.js');

    nodes.forEach((node) => {
        if (node.hasAttribute('data-tiptap-mounted')) {
            return;
        }

        node.setAttribute('data-tiptap-mounted', '');
        instances.set(node, mount(node));
    });
}

function unmount(node) {
    const instance = instances.get(node);

    if (instance) {
        instance.destroy();
        instances.delete(node);
    }

    node.removeAttribute('data-tiptap-mounted');
}

document.addEventListener('DOMContentLoaded', mountAll);

// htmx swaps content in/out routinely -- a posted moment/reply prepends/appends new markup
// elsewhere on the page, and a composer's own card can be replaced outright. Tear down any
// editor instance whose mount node is about to leave the DOM (a live ProseMirror instance left
// attached to a detached node is a leak, not just dead weight), then mount whatever new mount
// points a swap introduced.
document.body.addEventListener('htmx:before:swap', (event) => {
    const target = event.detail?.ctx?.target;

    if (!target) {
        return;
    }

    if (target.matches?.('[data-tiptap-editor]')) {
        unmount(target);
    }

    target.querySelectorAll?.('[data-tiptap-editor]').forEach(unmount);
});

document.body.addEventListener('htmx:after:swap', mountAll);
