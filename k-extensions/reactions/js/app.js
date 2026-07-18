// Flashes a vote/reaction button when its container's htmx swap settles -- mirrors the reaction
// glow on the marketing site's own live demo (kopling-landing/public/index.html). Listens on
// `document` rather than `document.body`: both the vote and rail partials swap themselves via
// `hx-swap="outerHTML"`, so by the time htmx fires the settle event the old container (and the
// button that triggered the request, if it lived inside it) is already detached -- htmx falls
// back to dispatching on `document` for a detached target, which never bubbles into `body`.
// Reading `event.detail.newContent` instead of `event.target`/bubbling sidesteps that entirely,
// and works the same way for the rail's own out-of-band swap (an independent settle per task).
document.addEventListener('htmx:after:settle', (event) => {
    const nodes = event.detail?.newContent ?? [];

    for (const node of nodes) {
        if (!node.querySelectorAll || !/^(votes|reactions)-/.test(node.id ?? '')) {
            continue;
        }

        node.querySelectorAll('.btn:not(.kop-radd)').forEach((btn) => {
            btn.classList.remove('kop-rpop');
            void btn.offsetWidth; // restart the animation
            btn.classList.add('kop-rpop');
        });
    }
});
