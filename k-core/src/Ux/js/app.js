import htmx from 'htmx.org';
import Alpine from 'alpinejs';

window.htmx = htmx;
window.Alpine = Alpine;

// htmx 4 renamed this event to the colon form and moved request headers under
// detail.ctx.request.headers (was detail.headers). Without this, htmx.ajax() calls
// carrying no @csrf hidden field (e.g. the reactions picker) POST without the token
// and 403.
document.body.addEventListener('htmx:config:request', (event) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (token) {
        event.detail.ctx.request.headers['X-CSRF-TOKEN'] = token;
    }
});

// `<x-k::modal>` uses the native <dialog> element for real focus-trapping (unlike Dropdown's
// Popover API), but <dialog> has no attribute-only opener the way Popover's `popovertarget`
// gives Dropdown -- this one delegated listener is the only JS any modal needs; closing needs
// none (the panel's own `<form method="dialog">` and native Escape both close it for free).
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-modal-show]');

    if (trigger) {
        document.getElementById(trigger.dataset.modalShow)?.showModal();
    }
});

Alpine.start();
