// Illustrative only -- demonstrates the compiled-asset pipeline (see CLAUDE.md's
// "How k-core ships compiled assets"): a real npm dependency an extension needs beyond
// what k-core already bundles (Alpine, htmx) would be imported here.
document.querySelectorAll('[data-kop-example-badge]').forEach((el) => {
    el.classList.add('kop-example-badge');
});
