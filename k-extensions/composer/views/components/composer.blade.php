@php
    use Kopling\Core\Content\Moment;
    use Kopling\Core\Extension\Manager;
    use Kopling\Core\Ux\Compose\Modes;
    use Kopling\Core\Ux\Context;
    use Kopling\Core\Ux\SlotResolver;
@endphp
@auth
    @php
        $context = new Context(subject: Moment::draft());
        // Seeded here rather than left to `ux/compose/modes.blade.php`'s own `x-init` (a nested
        // child element) to fill in -- Alpine reliably evaluates *this* element's own `x-data`
        // literal the instant it binds it, page-load or htmx-swapped alike, with no dependency
        // on a separate child element's `x-init` also having run yet. `partials/edit.blade.php`
        // mirrors this exact computation for the same reason.
        $defaultMode = SlotResolver::resolve(Modes::SLOT, app(Manager::class)->ux())->first()?->id;
    @endphp
    <div x-data="{
            open: false,
            active: '{{ $defaultMode }}',
            defaultMode: '{{ $defaultMode }}',
            dirty: {},
            reset() {
                this.$refs.editor.querySelector('[data-tiptap-editor]')?.kopEditor?.clear();
                this.dirty = {};
                this.active = this.defaultMode;
            },
         }"
         @focusin="open = true"
         @htmx:after:request="if (($event.detail?.ctx?.response?.status ?? 500) < 400) { open = false; reset(); $refs.form.reset() }"
         class="card bg-base-100 hair border border-base-300 rounded-box mb-4 shadow-sm">
        <form x-ref="form"
              hx-post="{{ route('kopling-core::community/compose.store') }}"
              hx-target="#moments-feed"
              hx-swap="afterbegin"
              class="divide-y divide-base-content/10">
            @csrf
            <input type="hidden" name="compose_mode" :value="active">

            @include('kopling-composer::components.moment-fields', ['context' => $context])
        </form>
    </div>
@endauth
