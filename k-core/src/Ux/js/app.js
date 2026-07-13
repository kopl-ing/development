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

Alpine.start();
