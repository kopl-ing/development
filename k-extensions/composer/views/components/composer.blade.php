@php
    use Kopling\Composer\Extension;
    use Kopling\Core\Content\Moment;
    use Kopling\Core\Extension\Manager;
    use Kopling\Core\Ux\Context;
    use Kopling\Core\Ux\SlotResolver;
@endphp
@auth
    @php
        $context = new Context(subject: Moment::draft());
        $fieldsEntries = SlotResolver::resolve('kopling-composer::compose.fields', app(Manager::class)->ux());
    @endphp
    <div x-data="{
            open: false,
            active: null,
            defaultMode: null,
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

            <x-k::card.top :context="$context" :slot="Extension::TOP_SLOT" />
            <x-k::card.body :context="$context" :slot="Extension::BODY_SLOT" />

            @if ($fieldsEntries->isNotEmpty())
                <div class="px-4 py-3 sm:px-6">
                    <x-k::portal.slot name="kopling-composer::compose.fields" />
                </div>
            @endif

            <x-k::card.footer :context="$context" :slot="Extension::FOOTER_SLOT" />
        </form>
    </div>
@endauth
