{{-- Compose-first: a calm one-line box that grows on focus into title (optional) + body.
     Posting hx-prepends the new moment onto #moments-feed and collapses back. Inline Alpine +
     htmx only — no bundled JS of this component's own (the editor's own JS is core's editor.js/
     editor-tiptap.js, mounted via the editor component below, see its own docblock for why it
     isn't an Alpine.data() component either). The [x-cloak] rule ships as css/app.css, linked onto
     Community pages via Extension::extendsPortals(). Registered unconditionally into the slot
     (see Extension::ux()) -- @auth is what hides this from a guest, not a Permission.

     The editor mounts on a contenteditable region, not a native <textarea>, so "open on focus"
     uses @focusin (bubbles) rather than @focus (doesn't), and "reset on cancel/post" needs the
     editor's own imperative clear() alongside $refs.form.reset() -- form.reset() only touches
     native form controls, which the editor's own contenteditable region isn't. --}}
@auth
    @php $me = auth()->user(); @endphp
    <div x-data="{
            open: false,
            clearEditor() {
                this.$refs.editor.querySelector('[data-tiptap-editor]')?.kopEditor?.clear();
            },
         }"
         @focusin="open = true"
         @htmx:after:request="if (($event.detail?.ctx?.response?.status ?? 500) < 400) { open = false; clearEditor(); $refs.form.reset() }"
         class="card bg-base-100 hair border border-base-300 rounded-box mb-4 shadow-sm">
        <form x-ref="form"
              hx-post="{{ route('kopling-core::community/compose.store') }}"
              hx-target="#moments-feed"
              hx-swap="afterbegin"
              class="card-body gap-3 p-4">
            @csrf
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 shrink-0 rounded-full bg-primary text-primary-content grid place-items-center text-sm font-bold">
                    {{ strtoupper(mb_substr($me->name ?? '?', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <input type="text" name="title" maxlength="150" x-show="open" x-cloak
                           placeholder="{{ __('kopling-composer::messages.title_placeholder') }}"
                           class="input input-sm w-full font-semibold px-0 mb-1 border-0 focus:outline-none bg-transparent">
                    <div x-ref="editor">
                        <x-k::editor name="body" placeholder="{{ __('kopling-composer::messages.body_placeholder') }}" />
                    </div>
                </div>
            </div>

            <div x-show="open" x-cloak class="flex items-center justify-end gap-2 pt-1">
                <button type="button" @click="open = false; clearEditor(); $refs.form.reset()"
                        class="btn btn-ghost btn-sm">{{ __('kopling-composer::messages.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    {{ __('kopling-composer::messages.post') }}
                </button>
            </div>
        </form>
    </div>
@endauth
