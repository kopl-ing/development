// Same lazy split as tag-input.js, but as a custom element: connect/disconnect follow every htmx
// swap and boosted navigation without listening to swap events.
class DateRange extends HTMLElement {
    async connectedCallback() {
        const { mount } = await import('./date-range-calendar.js');

        if (this.isConnected && !this.unmount) {
            this.unmount = mount(this);
        }
    }

    disconnectedCallback() {
        this.unmount?.();
        this.unmount = null;
    }
}

if (!customElements.get('k-date-range')) {
    customElements.define('k-date-range', DateRange);
}
