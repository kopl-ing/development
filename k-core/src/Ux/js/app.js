import htmx from 'htmx.org';
import Alpine from 'alpinejs';

window.htmx = htmx;
window.Alpine = Alpine;

document.body.addEventListener('htmx:configRequest', (event) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (token) {
        event.detail.headers['X-CSRF-TOKEN'] = token;
    }
});

Alpine.start();
