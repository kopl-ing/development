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
                this.$watch('quotes', () => this.$nextTick(() => this.syncPad()));
                window.addEventListener('resize', () => this.syncPad(), { passive: true });
                // htmx 4 renamed the lifecycle events to colon form and moved the payload to
                // detail.ctx (there is no detail.parameters / detail.successful anymore). Both
                // events bubble up from the form to this root.
                //
                // Prepend the multi-quote blocks to the reply body before the request is sent.
                // At config:request the body is still a FormData on ctx.request.body.
                this.$el.addEventListener('htmx:config:request', (e) => {
                    const prefix = this.quotesPrefix();
                    if (!prefix) return;
                    const body = e.detail?.ctx?.request?.body;
                    if (body && typeof body.get === 'function') {
                        body.set('body', prefix + (body.get('body') || ''));
                    }
                });
                // Collapse the composer once a reply posts successfully (status < 400).
                this.$el.addEventListener('htmx:after:request', (e) => {
                    const status = e.detail?.ctx?.response?.status;
                    if (status === undefined || status >= 400) return;
                    this.open = false;
                    if (this.$refs.body) this.$refs.body.value = '';
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
            openComposer() { this.open = true; this.$nextTick(() => { this.$refs.body?.focus(); this.syncPad(); }); },
            // Reserve bottom scroll space so the fixed dock never hides the last posts. Only the
            // visible half (bar or panel) has a box, so offsetHeight tracks the current state.
            syncPad() {
                const h = this.$el.offsetHeight || 0;
                document.body.style.paddingBottom = (h + 28) + 'px';
            },

            quotes: [],
            toggleQuote(d) {
                const i = this.quotes.findIndex(q => q.id === d.id);
                if (i >= 0) this.quotes.splice(i, 1);
                else this.quotes.push(d);
                this.emitQuotes();
                this.openComposer();
            },
            removeQuote(id) {
                const i = this.quotes.findIndex(q => q.id === id);
                if (i >= 0) this.quotes.splice(i, 1);
                this.emitQuotes();
            },
            emitQuotes() {
                window.dispatchEvent(new CustomEvent('kop-quotes-changed', { detail: { ids: this.quotes.map(q => q.id) } }));
            },
            quotesPrefix() {
                return this.quotes.length ? this.quotes.map(q => '> ' + q.author + ': ' + q.text).join('\n\n') + '\n\n' : '';
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
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
                        <span class="kop-dock__tool-lbl" x-text="following ? @js(__('kopling-reply-dock::messages.following')) : @js(__('kopling-reply-dock::messages.follow'))"></span>
                    </button>
                    <button type="button" class="kop-dock__tool">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                        <span class="kop-dock__tool-lbl">{{ __('kopling-reply-dock::messages.report') }}</span>
                    </button>
                    <button type="button" class="kop-dock__reply" @click="openComposer()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 17l-5-5 5-5"/><path d="M4 12h11a4 4 0 0 1 4 4v2"/></svg>
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
                        {{-- multi-quote blocks (dock-local state; event-driven, no Alpine store) --}}
                        <template x-if="quotes.length">
                            <div class="kop-dock__quotes">
                                <template x-for="q in quotes" :key="q.id">
                                    <div class="kop-dock__quote">
                                        <span class="kop-dock__quote-text"><b x-text="q.author"></b>: <span x-text="q.text"></span></span>
                                        <button type="button" class="kop-dock__quote-remove" @click="removeQuote(q.id)"
                                                title="{{ __('kopling-reply-dock::messages.remove_quote') }}">✕</button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <textarea x-ref="body" name="body" required rows="3"
                                  placeholder="{{ __('kopling-reply-dock::messages.placeholder') }}"
                                  class="kop-dock__textarea"></textarea>

                        {{-- canned replies --}}
                        <div class="kop-dock__canned">
                            <span class="kop-dock__canned-lbl">{{ __('kopling-reply-dock::messages.canned') }}</span>
                            @foreach ((array) __('kopling-reply-dock::messages.canned_items') as $canned)
                                <button type="button" class="kop-dock__chip"
                                        @click="$refs.body.value = ($refs.body.value ? $refs.body.value + '\n\n' : '') + @js($canned); $refs.body.focus()">{{ $canned }}</button>
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
