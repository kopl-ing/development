# htmx 4 opportunities audit

A pass over `k-core` and `k-extensions` looking for hand-rolled JS/full-page-reload patterns
that htmx 4 already covers natively, or existing `hx-*` usage that's subtly wrong. This is a
findings list, not a decision log — nothing here is a commitment, it's a backlog to work
through and prune. Priorities: **high** (hand-rolled logic doing exactly what htmx does
natively), **medium** (real improvement, some design nuance), **low** (stylistic only).

Already-idiomatic htmx 4 usage was reviewed and is *not* re-listed here: `k-core`'s
`community/loaded.blade.php`, `community/new-moments.blade.php`, `community/poll.blade.php`,
`editor/notion.blade.php`, `page/pagination.blade.php`; `k-extensions/reply-dock/dock.blade.php`
(the most sophisticated correct htmx-4 usage in the repo); `discussions`' composer/reply/quote
flows; `reactions`' `rail.blade.php`/`vote.blade.php` OOB pairing; `tags`, `poll`, `profile`,
`theme-delft`, `theme-midnight`, `style-guide`, `example` (nothing relevant or demo-only).

## Correctness bug (fix regardless of priority triage below)

### `pin` — dead htmx 1/2 event handler, dialog never auto-closes

- **File**: `k-extensions/pin/views/ux/control-entry.blade.php:8`
- **Current**: `hx-on::after-request="if (event.detail.successful) $el.closest('dialog').close()"`
  — this is htmx **1/2** syntax (dash-form event name, `event.detail.successful` payload shape).
  Neither exists in htmx 4, so the handler silently never fires; the dialog never auto-closes
  on a successful pin action.
- **Fix**: match the pattern already used correctly twice elsewhere in this repo —
  `k-extensions/discussions/views/components/composer.blade.php:23` and (equivalent) in
  `k-extensions/composer/views/components/composer.blade.php`:
  ```
  hx-on::after:request="if ((event.detail?.ctx?.response?.status ?? 500) < 400) { $el.closest('dialog').close() }"
  ```
- **Why**: this is exactly the htmx-1/2-vs-4 event-naming trap CLAUDE.md's own gotchas list
  warns about — worth fixing on sight rather than batching with the rest of this list.

## k-core

### 1. Icon picker search — JSON endpoint + client-side templating (high)

- **Files**: `k-core/resources/js/icon-picker.js:76-113` (`search()`/`renderResults()`),
  `k-core/src/Http/Controllers/IconSearchController.php`,
  `k-core/src/Ux/Form/IconSearch/FontAwesomeIconSearch.php`,
  `k-core/views/ux/form/icon-picker.blade.php`
- **Current**: `search()` does `fetch(...).then(r => r.json())` against
  `_xhr/kopling-core/icon-search`, then hand-builds `<button>` elements in JS from the JSON
  response. The controller already renders each result's SVG server-side
  (`FontAwesomeIconSearch::search()` → `IconRenderer::svg()`) before serializing to JSON — the
  server-rendered HTML exists and is thrown away in favor of a JS template that duplicates it.
- **htmx replacement**: `hx-get="{{ $searchUrl }}" hx-trigger="input changed delay:250ms"
  hx-target="find [data-icon-results]"` on the search input; controller returns a Blade
  fragment of `<button>`s instead of JSON. Deletes `renderResults()` and the manual fetch chain.
  Selection (write to hidden input, close popover) still needs a small `hx-on:click`/JS, so this
  isn't a 100% JS removal — but the network+templating round trip moves entirely to htmx.
- **Why**: removes a JSON-shaped exception to the project's own "server returns HTML fragments"
  convention (the endpoint already sits at the same `_xhr/kopling-core/...` path convention as
  `moments/latest`/`moments/load`, which *do* return HTML).

### 2. Modal open — global delegated JS listener (low)

- **Files**: `k-core/resources/js/app.js:19-29`, `k-core/views/ux/modal.blade.php:1`
  (`data-modal-show="{{ $id }}"`)
- **Current**: a global `document.addEventListener('click', ...)` in `app.js` delegates on
  `[data-modal-show]` to call `.showModal()` — necessary because `<dialog>` has no
  attribute-only opener the way `popovertarget` gives `Dropdown`.
- **htmx replacement**: `hx-on:click="document.getElementById('{{ $id }}').showModal()"`
  directly on the trigger, removing the delegated listener from `app.js`.
- **Why it's low priority**: same behavior, fewer lines — not a server round trip or DOM-sync
  problem htmx solves structurally. Pure style preference.

### Ruled out (not real opportunities)

- `tag-input-tagify.js:146` `fetch()` — required by Tagify's own async-whitelist API contract
  (JS objects, not HTML); forcing an HTML fragment here fights the widget.
- `editor.js`/`editor-tiptap.js`/`emoji-picker.js`/`emoji-picker-mart.js` — legitimate
  ProseMirror/emoji-mart wrapping; `editor.js` already uses `htmx:before:swap`/`htmx:after:swap`
  correctly for mount/unmount across htmx swaps.
- `app.js`'s `[data-href]` card-click-to-navigate listener — whole card isn't an anchor, and the
  exclusion logic (skip nested interactive elements, skip text selection) has no `hx-boost`
  equivalent.
- `ux/compose/modes.blade.php` — Alpine `x-show` between already-rendered, preloaded modes, not
  the lazy-fetch-per-tab pattern htmx's tabs idiom targets.
- `ux/modal.blade.php`'s validation-error self-reopen script — full-page-redirect flow; no htmx
  feature addresses re-opening a client-only `<dialog>` after a redirect.

## k-extensions

### admin — full-page-reload CRUD where the package's own htmx idiom already exists (high/medium)

`admin/views/components/settings/partials/card.blade.php:28-30` already does this correctly
(`hx-post` + `hx-target="closest .extension-card"` + `hx-swap="outerHTML"`) — the rest of the
package doesn't follow its own pattern:

- `drives/index.blade.php:16-26,64-81,82-86` — create/edit modals + delete all plain
  `<form method="POST">` with full reload; delete uses `onsubmit="return confirm(...)"` instead
  of `hx-confirm`. **High**.
- `groups/index.blade.php:7-12,29-33,40-51,53-57` — same pattern (create, rename, permissions
  modal, delete). **High**.
- `people/index.blade.php:36-47` — "manage groups" modal, full reload. **Medium**.
- `portals/index.blade.php:29-36,41-45` — path-update/reset-to-default forms, full reload for a
  single-row change. **Medium**.
- `storage/index.blade.php:39-56,75-79` — drive-mapping save/unmap, orphaned-mapping delete,
  full reload. **Medium**.

Fix: `hx-post`/`hx-target="closest tr"` (or a wrapping row id)/`hx-swap="outerHTML"`, `hx-confirm`
for deletes — same shape as `card.blade.php`'s existing pattern.

### pages — admin CRUD, same shape as `admin` above (high/medium)

- `admin/pages/edit.blade.php:57-66` — "move up"/"move down" section-reorder buttons are two
  separate full-page-reload forms just to swap two rows' order. **High** — textbook
  `hx-post` + `hx-target` (sections list container) + `hx-swap="outerHTML"` (or `innerMorph` to
  preserve any open row state).
- `admin/pages/create.blade.php`, `edit.blade.php:8-18`, `index.blade.php:43-47`,
  `admin/section-templates/index.blade.php:12-19,46-59,60-64` — same full-page-reload CRUD
  pattern as `admin`'s index pages (create/edit modals, `onsubmit` confirm on delete).
  **Medium** — worth doing together with the `admin` findings for consistency.

### docs — full page reload on every sidebar nav click (medium)

- **File**: `k-extensions/docs/views/ux/sidebar.blade.php:4-9`
- **Current**: plain `<a>` links in the persistent docs sidebar (rendered every page via
  `Community\Chrome`) — every click reloads the whole page including chrome that never changes.
- **htmx replacement**: `k-core/views/page/pagination.blade.php:17` already establishes the
  `hx-boost:inherited="true"` idiom in this codebase for exactly this "keep the shell, swap the
  content" case.
- **Why medium not high**: needs the boost scoped narrowly to the `<nav>` itself, not the whole
  sidebar/chrome, per this project's own hx-boost scoping guidance in CLAUDE.md — a deliberate
  scoping decision, not a blind wrap.

### auth-email-password — full-page reload on validation failure (medium)

- **Files**: `login-form.blade.php:1`, `registration-form.blade.php:1`
- **Current**: plain `method="POST"` forms; a validation failure re-renders the whole page.
- **htmx replacement**: `hx-post` + `hx-status:422="target:#errors"` for inline errors without
  reload; success path uses `HX-Redirect` server-side for post-login/registration navigation.
- **Why medium not high**: real improvement, but the session/auth redirect flow around it
  deserves a deliberate design pass rather than a mechanical swap.

### reactions — imperative `htmx.ajax()` instead of declarative attributes (medium)

- **File**: `k-extensions/reactions/views/components/modal.blade.php:43,59`
- **Current**: the word-reaction picker calls `window.htmx.ajax('POST', url, { target, swap:
  'outerHTML', values: {...} })` imperatively from two separate Alpine handlers (Enter-key and
  click) — bypasses `hx-indicator`/`hx-disable` and duplicates the call twice.
- **htmx replacement**: bind `hx-post`/`hx-target` declaratively via Alpine
  (`x-bind:hx-post="url"`) on one element with `hx-trigger="click, keydown[key=='Enter']
  from:input"`.
- **Why medium not high**: the modal is a single instance reused for many reactables (url/target
  only known at open time), so the fully declarative version is a real design change, not a
  drop-in swap.

### widgets — "live" pulse counts that never re-fetch (medium)

- **Files**: `k-extensions/widgets/views/ux/pulse-widget.blade.php`,
  `k-extensions/widgets/src/Ux/PulseWidget.php:36`
- **Current**: docblock describes the widget as "a few live counts," server-cached 60s — but the
  client never re-fetches, so numbers freeze at page-load time indefinitely past the cache's own
  freshness window.
- **htmx replacement**: `hx-trigger="every 60s"` self-`hx-get` on the widget wrapper, matched to
  the cache TTL.
- **Why medium**: clear mismatch between stated intent and actual behavior, but low-stakes
  (vanity stats, not core functionality).

### Ruled out (not real opportunities)

- `thread-title/sticky.blade.php` — pure scroll-based CSS toggle, no network round trip;
  htmx's `revealed`/`intersect` triggers exist to *fetch on scroll*, not to apply this.
- `reply-dock/dock.blade.php`'s cross-subtree `htmx:after:settle` listener — the documented
  correct workaround for htmx 4's "outerHTML swap of an ancestor never bubbles" quirk (see
  CLAUDE.md gotchas), not a gap.
- `discussions`' `kop-quote-toggle`/`kop-quotes-changed` custom events — pure client-side
  cross-component signaling, no server round trip involved.
- `composer`, `demo`, `poll`, `profile`, `style-guide` (demo/reference code), `theme-delft`,
  `theme-midnight`, `example` (illustrative only) — reviewed, nothing relevant or already
  idiomatic.

## Suggested order of attack

1. [done] Fix the `pin` dead-handler bug (correctness, trivial diff).
2. [done] `admin` + `pages` CRUD forms → htmx (`hx-boost` on the existing create/edit/delete
   forms — matches the `hx-boost:inherited` idiom already used by `page/pagination.blade.php`,
   swaps the whole body on both success and validation-error responses with zero controller
   changes; `hx-confirm` replacing `onsubmit="return confirm(...)"` on deletes. The one exception
   is `pages`' section reorder buttons, done as the audit's own "textbook" fragment-swap case
   instead: sections list extracted into `pages/views/admin/pages/partials/sections-list.blade.php`,
   `PageSectionsController::move()` returns that partial directly for `HX-Request` requests,
   `hx-target="#page-sections-list"`/`hx-swap="outerHTML"` on the move forms).
3. [done] Icon picker JSON→HTML fragment endpoint. `IconSearchController` now returns
   `ux/form/icon-picker-results.blade.php` (a fragment covering the prompt/no-results/results
   states) instead of `JsonResponse`; `icon-picker.js`'s `renderResults()`/manual `fetch()` are
   gone, replaced by `hx-get`/`hx-trigger="input changed delay:250ms, load"`/`hx-target` set on
   the dynamically-created search input, initialized via `htmx.process()`. Selection reads
   `data-icon-id` + the button's own rendered SVG off the server-rendered `[data-icon-option]`
   buttons instead of a JS-held icon object. `FontAwesomeIconSearchTest`'s two controller-level
   tests updated for the new HTML contract (they asserted `assertExactJson`/`assertJsonFragment`
   before).
4. [done] `docs` sidebar `hx-boost:inherited="true"` on the `<ul class="menu">` (narrowest scope,
   no explicit target -- defaults to swapping `document.body`, matching the "keep the shell
   running" idiom `page/pagination.blade.php` already established).
   [done] `widgets` pulse polling: `PulseWidget`'s card now carries `hx-get`/`hx-trigger="every
   60s"`/`hx-swap="outerHTML"` self-targeting itself. Required promoting the `widgets` extension
   to `ExtendsPortals` (attached to `kopling-core::community`, its own `routes/web.php` added)
   since it previously had no routes of its own -- `_xhr/kopling-widgets/pulse` re-renders the
   same `PulseWidget` component the sidebar slot uses on first load. New
   `tests/Feature/Widgets/PulseWidgetTest.php`.
   [done] `reactions` modal: the word-reaction submit button now carries `hx-post`/`hx-target`
   (Alpine-bound, `url`/`target` are only known once `kop-react-open` fires) instead of two
   separate `window.htmx.ajax()` calls; the Enter-key-in-input path forwards into a click on that
   same button (`$refs.submit.click()`) rather than duplicating the wiring. Required an explicit
   `window.htmx.process($refs.submit)` after each open (`$nextTick`, once Alpine has written the
   real attribute values) -- htmx only scans `hx-*` attributes once, at `process()` time, and the
   button has none at initial page load since `url`/`target` start `null` and Alpine's `x-bind`
   omits an attribute entirely for a null value; confirmed against `htmx.js` source
   (`#queryEltAndDescendants`/`#actionSelector`/`#shouldInitialize`) rather than assumed.
   [done] `auth-email-password` inline validation: on reflection, `hx-status:422` wasn't the
   right fit -- both `LoginController::login()`'s failure path (a thrown `ValidationException`,
   caught by Laravel's default handler into a redirect-back-with-errors) and its success path
   (`redirect()->intended(...)`) were already plain redirects, and `RegistrationController`
   mirrors the same shape. `hx-boost="true"` on both forms gets the same "avoid a full reload"
   win with zero controller changes, matching the `admin`/`pages` CRUD forms and `docs` sidebar
   above rather than the heavier inline-error redesign originally proposed.

All seven items above are done. Everything mechanical (`hx-boost` on forms/nav that were already
just doing full-page redirects either way) reused the exact same idiom `page/pagination.blade.php`
had already established, needing no controller changes; the two genuine fragment-swap builds
(pages section reorder, icon picker search) and the two JS/Alpine correctness fixes (pin's dead
htmx-1/2 handler, reactions' declarative-attribute reprocessing) got their own dedicated
controller/JS work as described inline above.
