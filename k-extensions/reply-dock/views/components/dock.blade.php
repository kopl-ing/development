@php
    use Kopling\Core\Content\Moment;

    // Discussion page + signed-in only. On the feed the route has no {moment}; a guest keeps
    // discussions' own "log in to reply" line (its form only renders for auth anyway).
    $moment = request()->route('moment');
    $me = auth()->user();
@endphp

@if ($moment instanceof Moment && $me)
    {{-- The "post scrubber" dock: a sticky pill with a reading-position counter + a draggable
         scrubber, Follow / Report / Reply, that morphs into a composer (quote blocks + canned
         replies). All logic is INLINE x-data (extension js can't register an Alpine component
         before core's Alpine.start()); styling is css/app.css (extension utility classes aren't
         in core's compiled build). Store refs use `?.` so they survive the quotes store
         registering a beat late. --}}
    <div class="kop-dock"
         x-data="{
            open: false,
            following: false,
            count: 1,
            current: 1,
            progress: 0,
            scrubbing: false,
            init() {
                this.recount();
                this.onScroll();
                window.addEventListener('scroll', () => this.onScroll(), { passive: true });
                // The dock is position:fixed over the bottom of the page, so the last posts sit
                // behind it. Reserve scroll space equal to the dock's live height (the composer is
                // far taller than the collapsed bar) so everything can scroll clear above it.
                this.syncPad();
                this.$watch('open', () => this.$nextTick(() => this.syncPad()));
                window.addEventListener('resize', () => this.syncPad(), { passive: true });
                // htmx 4 renamed the lifecycle events to colon form and moved the payload to
                // detail.ctx (there is no detail.parameters / detail.successful anymore). Both
                // events bubble up from the form to this root.
                //
                // Collapse the composer once a reply posts successfully (status < 400). The
                // editor mounts on a contenteditable region, not a native <textarea> -- clearing
                // it needs its own imperative clear(), not $refs.body.value = ''.
                this.$el.addEventListener('htmx:after:request', (e) => {
                    const status = e.detail?.ctx?.response?.status;
                    if (status === undefined || status >= 400) return;
                    this.open = false;
                    this.editorEl()?.kopEditor?.clear();
                    this.quotes = [];
                    this.emitQuotes();
                });
                // Recount once the new reply is actually swapped into the list.
                this.$el.addEventListener('htmx:after:swap', () => { this.recount(); this.syncPad(); });
            },
            recount() {
                const wrap = document.getElementById('replies-{{ $moment->id }}');
                this.count = (wrap ? wrap.querySelectorAll('.chat').length : 0) + 1;
                this.onScroll();
            },
            onScroll() {
                if (this.scrubbing) return;
                const h = document.documentElement.scrollHeight - window.innerHeight;
                this.progress = h > 0 ? Math.min(1, Math.max(0, window.scrollY / h)) : 0;
                this.current = Math.min(this.count, Math.max(1, Math.round(this.progress * (this.count - 1)) + 1));
            },
            scrub(e) {
                const rect = e.currentTarget.getBoundingClientRect();
                const to = (x) => {
                    const f = Math.min(1, Math.max(0, (x - rect.left) / rect.width));
                    this.progress = f;
                    window.scrollTo(0, f * (document.documentElement.scrollHeight - window.innerHeight));
                };
                this.scrubbing = true;
                to(e.clientX);
                const move = (ev) => to(ev.clientX);
                const up = () => { this.scrubbing = false; window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); this.onScroll(); };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },
            openComposer() { this.open = true; this.$nextTick(() => { this.editorEl()?.querySelector('.ProseMirror')?.focus(); this.syncPad(); }); },
            // The mount point rendered by the editor component inside x-ref="editor" --
            // editor-tiptap.js exposes its imperative API as `.kopEditor` directly on this
            // element (see its own mount() docblock), not through an Alpine ref of its own.
            editorEl() { return this.$refs.editor?.querySelector('[data-tiptap-editor]') ?? null; },
            // Reserve bottom scroll space so the fixed dock never hides the last posts. Only the
            // visible half (bar or panel) has a box, so offsetHeight tracks the current state.
            syncPad() {
                const h = this.$el.offsetHeight || 0;
                document.body.style.paddingBottom = (h + 28) + 'px';
            },

            // Tracks which reply ids are "currently quoted" purely for the +Quote/−Quote label
            // on each reply's own button (kop-quotes-changed) -- it no longer double as a
            // pending-quotes buffer serialized at submit time. A quote is now inserted as a real,
            // directly-editable blockquote node the moment it's toggled on; toggling the same
            // reply's button off only forgets the label state, it doesn't reach back into the
            // document to remove that blockquote -- the editor is the actual document now, not a
            // hidden string prefix, so removing a quote the user changed their mind about is just
            // deleting it in the editor like any other content.
            quotes: [],
            toggleQuote(d) {
                const i = this.quotes.findIndex(q => q.id === d.id);
                if (i >= 0) {
                    this.quotes.splice(i, 1);
                } else {
                    this.quotes.push(d);
                    this.editorEl()?.kopEditor?.insertQuote({ author: d.author, text: d.text });
                }
                this.emitQuotes();
                this.openComposer();
            },
            emitQuotes() {
                window.dispatchEvent(new CustomEvent('kop-quotes-changed', { detail: { ids: this.quotes.map(q => q.id) } }));
            },
         }"
         @kop-quote-toggle.window="toggleQuote($event.detail)">
        <div class="kop-dock__inner">

            {{-- ========= collapsed: the scrubber bar ========= --}}
            <div class="kop-dock__bar" x-show="!open" x-cloak>
                <span class="kop-dock__count"><span x-text="current"></span>/<span x-text="count"></span></span>

                <div class="kop-dock__scrub" @pointerdown.prevent="scrub($event)" title="{{ __('kopling-reply-dock::messages.scrub') }}">
                    <div class="kop-dock__scrub-track"></div>
                    <div class="kop-dock__scrub-fill" :style="`width:${progress * 100}%`"></div>
                    <div class="kop-dock__scrub-thumb" :style="`left:${progress * 100}%`"></div>
                </div>

                <div class="kop-dock__tools">
                    <button type="button" class="kop-dock__tool" :class="following && 'is-on'" @click="following = !following">
                        <x-k::icon name="kopling-reply-dock::follow" width="14" height="14" />
                        <span class="kop-dock__tool-lbl" x-text="following ? @js(__('kopling-reply-dock::messages.following')) : @js(__('kopling-reply-dock::messages.follow'))"></span>
                    </button>
                    <button type="button" class="kop-dock__tool">
                        <x-k::icon name="kopling-reply-dock::report" width="14" height="14" />
                        <span class="kop-dock__tool-lbl">{{ __('kopling-reply-dock::messages.report') }}</span>
                    </button>
                    <button type="button" class="kop-dock__reply" @click="openComposer()">
                        <x-k::icon name="kopling-reply-dock::reply" width="14" height="14" />
                        {{ __('kopling-reply-dock::messages.reply') }}
                    </button>
                </div>
            </div>

            {{-- ========= expanded: the composer ========= --}}
            <form x-show="open" x-cloak
                  hx-post="{{ route('kopling-core::community/discussions.reply', $moment->id) }}"
                  hx-target="#replies-{{ $moment->id }}"
                  hx-swap="beforeend"
                  @keydown.escape="open = false"
                  @keydown.meta.enter="$el.requestSubmit()" @keydown.ctrl.enter="$el.requestSubmit()"
                  class="kop-dock__panel">
                @csrf
                <div class="kop-dock__composer">
                    <span class="kop-dock__avatar">{{ strtoupper(mb_substr($me->name ?? '?', 0, 1)) }}</span>
                    <div class="kop-dock__field">
                        {{-- Quoting a reply inserts a real blockquote directly into the editor
                             below (see toggleQuote()) rather than staging it in a removable chip
                             list -- the editor is the document now, so unquoting is just deleting
                             it there like any other content. --}}
                        <div x-ref="editor">
                            <x-k::editor name="body" placeholder="{{ __('kopling-reply-dock::messages.placeholder') }}" />
                        </div>

                        {{-- canned replies --}}
                        <div class="kop-dock__canned">
                            <span class="kop-dock__canned-lbl">{{ __('kopling-reply-dock::messages.canned') }}</span>
                            @foreach ((array) __('kopling-reply-dock::messages.canned_items') as $canned)
                                <button type="button" class="kop-dock__chip"
                                        @click="editorEl()?.kopEditor?.insertText(@js($canned))">{{ $canned }}</button>
                            @endforeach
                        </div>

                        <div class="kop-dock__actions">
                            <span class="kop-dock__hint"><kbd>Ctrl</kbd>/<kbd>⌘</kbd>+<kbd>Enter</kbd> {{ __('kopling-reply-dock::messages.to_post') }}</span>
                            <button type="button" @click="open = false" class="btn btn-ghost btn-sm">{{ __('kopling-reply-dock::messages.cancel') }}</button>
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('kopling-reply-dock::messages.post') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif
