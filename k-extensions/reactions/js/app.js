// Plain ES module, linked onto Community pages via Extension::extendsPortals()'s ->js().
// Defines the Alpine store that drives the reaction picker modal: the rail's "+" button on any
// card calls show(url, target); the one page-level modal (views/components/modal.blade.php)
// reads this store, and submit() posts emoji + optional word via htmx (core adds the CSRF
// header for every htmx request, htmx.ajax included), swapping that card's "Latest reactions"
// strip in place.
document.addEventListener('alpine:init', () => {
    window.Alpine.store('reactions', {
        open: false,
        url: null,
        target: null,
        emoji: null,
        word: '',

        show(url, target) {
            this.url = url;
            this.target = target;
            this.emoji = null;
            this.word = '';
            this.open = true;
        },

        close() {
            this.open = false;
        },

        submit() {
            if (!this.emoji || !this.url) return;
            window.htmx.ajax('POST', this.url, {
                target: this.target,
                swap: 'outerHTML',
                values: { emoji: this.emoji, word: this.word },
            });
            this.close();
        },
    });
});
