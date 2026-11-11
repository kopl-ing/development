// Same lazy split as tag-input.js, but as a custom element: connect/disconnect follow every htmx
// swap and boosted navigation without listening to swap events.
class KCalendar extends HTMLElement {
    async connectedCallback() {
        const { mount } = await import('./calendar-vcp.js');

        if (this.isConnected && !this.unmount) {
            this.unmount = mount(this);
        }
    }

    disconnectedCallback() {
        this.unmount?.();
        this.unmount = null;
    }
}

if (!customElements.get('k-calendar')) {
    customElements.define('k-calendar', KCalendar);
}
