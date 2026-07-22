# Plan: Pages + Docs as reusable Portal extensions

Working plan for rebuilding kopl.ing's landing + docs on Kopling's own Portal system, as two
reusable extensions (`kopling/pages`, `kopling/docs`) rather than the static HTML in
`../kopling-landing`. Captures the design worked out in chat so it survives across sessions —
update the checkboxes and "Done" notes as steps land, don't rewrite history above them.

Mark steps `[x]` with a one-line **Done:** note (what + where) as they're completed. Leave
undone steps as `[ ]`. This file is the resume point — read it first in any session picking this
back up.

---

## Sequencing

1. **Storage: `drives` + `storage_mappings` tables, `Resolver`, admin UI** — foundational, blocks
   Docs entirely, useful independently of this effort. **Fully done as of 2026-07-22.**
2. **Portal path override** — independent of storage, blocks Pages ever owning `/`. **Fully done
   as of 2026-07-22.**
3. `kopling/pages` — depends on 2 (done). **Mechanism done as of 2026-07-22**; content
   migration still open.
4. `kopling/docs` — depends on 1 (done). **Mechanism done as of 2026-07-22**; seed content
   still open.

**All four mechanism steps are done.** What's left across the whole plan: Pages' seed content
(rewrite of `index.html`), Docs' seed content (split of `charter.html`/`extend.html`), the
storage/portals admin UI's own known limitations noted in their sections above, and the open
questions below (what happens to the `kopling-landing` repo, decision-log dual-source risk
during transition).

**Known environment gap, unrelated to this plan:** `public/build/manifest.json` doesn't exist in
this dev checkout (`npm run build` never run), so any Feature test rendering a full page via the
shared layout's `@vite()` call fails. Pre-existing on `main` before this plan started (confirmed
via `git stash` comparison and via `SettingsControllerTest`'s own equivalent test failing
identically). Not something to "fix" as part of this plan — just don't mistake one of these for a
real regression when running the suite.

---

## 1. Storage: drives + resolver

Replaces the earlier "reuse `config('filesystems.disks')` + Settings KV" idea — rejected because
credentials/config belong in dedicated, queryable tables, not a stringly-typed KV blob, and
because it lets the general `settings` table be reset without taking out storage config as
collateral damage.

Terminology: **drive**, not "filesystem" — matches `.docs/planning/decisions.md`'s own wording
("the request→drive resolver") and avoids a class-name collision with the `Filesystem`
contract/instance the resolver itself returns.

- [x] `drives` table — `k-core/migrations/2026_07_22_000100_create_drives_table.php`
  - `id` uuid primary
  - `name` string
  - `driver` string (`local` | `s3`, closed set for now)
  - `settings` json — driver-specific bag; string values prefixed `env:NAME` resolve via `env()`
    at read time only, never persisted/displayed resolved
  - `supports_public`, `supports_signed`, `writable` — plain booleans (not JSON: fixed small set
    mirrored from `StorageAccess`/`StorageRetention`/`StoragePermission`, needs to stay queryable
    across MySQL/MariaDB/Postgres/SQLite)
  - `enabled` boolean default true — disable without deleting, so a mapping pointing at it
    becomes visibly stale instead of orphaned by cascade
  - timestamps
  - **Done:** migrated as written above.
- [x] `storage_mappings` table — `k-core/migrations/2026_07_22_000101_create_storage_mappings_table.php`
  - `request_id` string **primary key** — the already-prefixed `StorageRequest` id
    (`kopling-docs::content`), not a surrogate uuid
  - `drive_id` foreignUuid → `drives.id`, `restrictOnDelete()` (never silently orphan a mapping)
  - `prefix` string nullable — sub-path scoping within the drive
  - timestamps
  - **Row absence = unmapped**, deliberately — no nullable-FK + status flag. Makes "declared but
    unmapped" and "mapped but no longer declared (stale)" both plain set-diff queries against
    `Manager::storageDrivers()`'s live ids.
  - **Done:** migrated as written above.
- [x] `Kopling\Core\Storage\Drive` model — `k-core/src/Storage/Drive.php` (extends
  `Kopling\Core\Database\Model`, `HasUuids`, casts for `settings`/booleans)
  - **Done:** implemented, `mappings(): HasMany`.
- [x] `Kopling\Core\Storage\StorageMapping` model — `k-core/src/Storage/StorageMapping.php`
  (non-incrementing string PK `request_id`, `belongsTo(Drive::class)`)
  - **Done:** implemented.
- [x] `Kopling\Core\Storage\ReadOnlyFilesystemAdapter` — `k-core/src/Storage/ReadOnlyFilesystemAdapter.php`,
  decorates `Illuminate\Contracts\Filesystem\Filesystem`, delegates reads, throws on
  put/putFile/putFileAs/writeStream/setVisibility/prepend/append/delete/copy/move/makeDirectory/deleteDirectory
  - **Done:** implemented as written above.
- [x] `Kopling\Core\Storage\Resolver` — `k-core/src/Storage/Resolver.php`
  - `resolve(string $requestId): Filesystem`
  - looks up the declared `StorageRequest` via `Manager::storageDrivers()` (throws if no
    extension declares it)
  - looks up `StorageMapping` (throws if unmapped, or mapped drive `enabled = false`) — **never
    silently falls back**, per the existing decisions.md commitment on this contract
  - builds the disk via `Storage::build([...$drive->settings, 'driver' => $drive->driver])`,
    resolving `env:` values first
  - wraps in `ReadOnlyFilesystemAdapter` when the **request's** declared `StoragePermission` is
    `ReadOnly` — stays a property of what the extension asked for, independent of `drives.writable`
    (a writable drive can still host a read-only-declared purpose)
  - **Done:** implemented. One design change from the original write-up: `Portal`-style
    `->createScopedDisk()` doesn't exist on this Laravel version (13) — prefix scoping instead
    goes through Laravel's own generic `'prefix'` config key (`FilesystemManager::createFlysystem()`
    wraps the built adapter in `League\Flysystem\PathPrefixing\PathPrefixedAdapter` when
    present), which needed a new dependency: added `league/flysystem-path-prefixing` to
    `k-core/composer.json`. Installed correctly via `composer update league/flysystem-path-prefixing`
    from the **root** — running `composer require` directly inside `k-core/` first (wrong) created
    a stray `k-core/vendor`+`k-core/composer.lock`, since `k-core` is a path-repo, not its own
    project root; removed both before redoing it from root, per the existing gotcha note in
    root `CLAUDE.md` about path-repo `composer.json` changes needing `composer update <package>`
    from root.
- [x] Tests — `tests/Feature/Storage/ResolverTest.php`: unmapped throws, disabled-drive throws,
  local-driver round-trip (write via an unmapped-permission drive, read back), `ReadOnly`
  request rejects writes even against a `writable = true` drive, `env:` resolution (set/unset
  env var), scoped prefix isolates reads
  - **Done:** all 8 cases pass. Added `tests/Fixtures/Extensions/ReadOnlyStorageRequester/` (a
    `ReadOnly`-permission `StorageRequest` fixture) alongside the existing `StorageRequester`
    fixture, following `tests/Feature/Admin/SettingsControllerTest.php`'s `app()->instance(Manager::class,
    fakeManager([...]))` swap pattern. Full suite run before/after: same 61 pre-existing failures
    on `main` (missing Vite build manifest + one unrelated flaky widget test, confirmed via
    `git stash` — not caused by this work), 247 → 255 passing with these added, 0 new failures.
- [x] Admin UI
  - Drives CRUD — `k-extensions/admin`, same shape as `PeopleController`/`GroupsController`
  - Storage-mappings list — one row per `Manager::storageDrivers()` entry, `Select` (eligible
    drives only — hard-filtered by `supports_public`/`supports_signed`/`writable` against the
    request's declared `StorageAccess`/`StoragePermission`) + `Input` (prefix)
  - **Done:**
    - `k-extensions/admin/src/Controllers/DrivesController.php` — index/store/update/destroy;
      `settings` JSON textarea validated server-side (rejects invalid JSON via
      `ValidationException`); `destroy()` catches the `QueryException` from
      `storage_mappings.drive_id`'s `restrictOnDelete()` into a normal form error
      (`drive_in_use`) instead of a raw 500.
    - `k-extensions/admin/src/Controllers/StorageMappingsController.php` — index computes
      `declared vs mapped` both directions (per-request eligible-drives list, plus an
      `orphaned` list: mapped `request_id`s no installed extension declares anymore); store
      does `updateOrCreate` keyed on `request_id`; destroy takes `request_id` from the POST
      body rather than a route-bound model (a `StorageMapping`'s PK contains `::`, not worth
      fighting route-segment encoding for).
    - Views: `views/drives/index.blade.php` (list + `<x-k::modal>` create/edit forms, using
      `<x-k::form.input>`/`.select`/`.text-area`/`.toggle` throughout — the settings page's own
      convention, not `GroupsController`'s plainer inline-input style, since Drive has enough
      fields to want the structured components) and `views/storage/index.blade.php`
      (one row per declared request + eligible-drive `<select>`, plus an "Orphaned mappings"
      section for stale rows).
    - Routes added to `k-extensions/admin/routes/web.php` under the existing
      `can:kopling-admin::manage-settings` group — same gate as Settings, no new permission
      (Drives/storage-mapping management is site configuration, same capability class).
    - Nav entries (`drives`, `storage`, both `when('manage-settings')`) added to
      `Extension::ux()`, ordered after `settings`.
    - Lang strings added to `k-extensions/admin/lang/en/messages.php`.
    - Tests: `tests/Feature/Admin/DrivesControllerTest.php` (8 tests),
      `tests/Feature/Admin/StorageMappingsControllerTest.php` (5 tests, exercised against
      `kopling/example`'s real already-declared `avatars` `StorageRequest` rather than a
      fixture — no isolation concern at this layer, and it's a real end-to-end check).
      3 of the 13 fail on a full-page `->get()` render — **pre-existing environment gap, not a
      regression**: `public/build/manifest.json` doesn't exist in this dev checkout (no
      `npm run build` run), and `tests/Feature/Admin/SettingsControllerTest.php`'s own
      equivalent list-page test fails identically on `main`, confirmed by running it standalone.
      Full-suite before/after: 61→64 failed (all 3 new failures this same pre-existing pattern),
      255→265 passed, 0 unexplained regressions.

## 2. Portal path override — done, 2026-07-22

- [x] `Portal::$path` — drop `readonly` (mirrors `$id`, which `Manager::portals()` already
  mutates for prefixing) — `k-core/src/Portal/Portal.php`
- [x] `Portal::$defaultPath` — new readonly property, the declared value, kept alongside the
  now-mutable `$path` so admin UI can show "default vs override" without Manager having
  overwritten the only copy
  - **Done:** `toArray()`/`fromArray()` updated to round-trip `defaultPath` too (falls back to
    `path` for old cache data missing the key, so a stale cache doesn't hard-break).
- [x] Settings key convention: `core.portal_path.{portal-id}` — override applied as a **final
  map step after `RegistrationCache` retrieval** in `Manager::portals()`, on both the cached and
  live branches — never baked into the cache itself, since the cache is a Composer-boundary
  snapshot and the override is live-editable admin data
  - **Done:** `Manager::applyPortalPathOverrides()`, always resolves `Settings::get("core.portal_path.{id}", $portal->defaultPath)` — resolving from `defaultPath`, never the portal's
    current `path`, is what makes this safe to run unconditionally even against a `Portal`
    reconstructed from a stale `RegistrationCache` entry (`CacheRegistrations` calls
    `Manager::portals()` too, so whatever it bakes into the cache as `path` gets immediately
    re-overwritten by this same step on every subsequent read regardless). Verified directly in
    `tests/Feature/Portal/PortalPathOverrideTest.php`'s "stale cache" test — a hand-built
    `RegistrationCache` entry with an intentionally-stale `path` still resolves to the current
    Settings override.
  - Added `Settings::forget(string $key)` (`k-core/src/Settings/Settings.php`) — the KV store
    only had `get`/`set` before; a reset-to-default action needs to remove the row outright, not
    `set()` it to the default value (which would leave an inert row behind).
- [x] `admin/portals` — new `PortalsController` + view, same list-with-input-per-row shape as
  the storage-mappings page above; validates path uniqueness across every portal's *current
  effective* path before writing the Setting (fail loud at save time, never guess at resolve
  time)
  - **Done:** `k-extensions/admin/src/Controllers/PortalsController.php` (index/update/reset —
    `id`/`path` travel in the POST body, same reasoning as `StorageMappingsController`: a
    Portal id already contains `::`). `views/portals/index.blade.php` — default path shown
    read-only, current path in an editable input, an "Overridden" badge + reset button when it
    differs from the default. Nav entry added, gated `manage-settings`, after `storage`.
    Tests: `tests/Unit/Extension/ManagerPortalTest.php` (+1, `defaultPath` wiring in a bare
    `fakeManager()`), `tests/Feature/Portal/PortalPathOverrideTest.php` (3, including the stale-
    cache case above), `tests/Feature/Admin/PortalsControllerTest.php` (5). Full suite before/
    after: 327→336 passed, same 3 pre-existing failures (card/logo/avatar-widget, unrelated),
    0 regressions.

## 3. `kopling/pages` — mechanism done, 2026-07-22; content migration still open

New extension, admin-authored pages CMS (replaces the earlier "static `kopling/landing`
extension" idea — rejected because hardcoded marketing copy isn't reusable to other
communities the way an admin-editable builder is).

**Built:** `k-extensions/pages` (registered in root `composer.json`). `Page`/`PageSection`
Eloquent models, `SectionKind` enum (`rich-text`/`hero`). Public Portal
`kopling-pages::pages` (`path: 'pages'`, ungated) with its own thin
`views/layouts/pages.blade.php` (topbar+footer wrapping `<x-k::portal.layout>` directly, not
`Community\Chrome`) — nav queries `Page::where('published', true)->where('show_in_nav', true)`
directly, not Ux-slot-driven. `PageController::index()`/`show()` render a page's sections in
order; a hero section renders the *page's own* `title` as its heading (no separate hero title
field), a rich-text section renders `content_html`. Admin CRUD (`PagesController`,
`PageSectionsController`) attached into the **existing** Admin portal via `ExtendsPortals`
targeting `kopling-admin::admin` — same "attach into a portal you don't own" shape Tags/
Reactions/Poll already use for their own admin screens, not a bolted-on admin page inside
Pages' own public portal. Gated behind a new `manage-pages` permission (separate from Admin's
own `access-admin`/`manage-settings`). Rich-text sections reuse `DocumentRenderer`/
`ValidDocument`/`<x-k::editor>` exactly as `Moment::$body`/`$body_html` do — no second
sanitization codepath. Section reordering is a simple order-swap with the adjacent neighbor
(`PageSectionsController::move()`), not drag-and-drop. `{page}/sections/{section}` routes use
`Route::scopeBindings()` so a section id from a different page 404s instead of silently
succeeding. Setting a page `is_index` auto-unsets it on every other page (no blocking
validation — friendlier for a single-admin toggle). 25 new tests across
`tests/Feature/Pages/PublicPageTest.php`, `tests/Feature/Admin/PagesControllerTest.php`,
`tests/Feature/Admin/PageSectionsControllerTest.php`. Full suite: 336→356 passed, same 3
pre-existing unrelated failures, 0 regressions.

**Known gap, not solved here:** Pages' topbar renders `UserMenu` correctly for a signed-in
person, but there's currently no way for a **guest** to see login/register links on this
portal — `auth-email-password` hardcodes its `login-link`/`register-link` registration to
`kopling-core::community.topbar` specifically, and there's no portal-scoped slot
generalization for "any public portal wants an auth widget" yet (same underlying gap
roadmap.md's "Ux / extensibility" section already flags). Solving it generally is bigger than
this extension's own scope — noted, not fixed.

**Still open:**
- [ ] Content migration: rewrite `../kopling-landing/public/index.html`'s sections
  (whatis/principles/stack/extend/governance/founder/support) as seed Pages content (a
  homepage-index Page with a hero section + rich-text sections), not ported verbatim — real
  writing work, not mechanism work; do this through the admin UI itself once it's the thing
  being exercised for real, rather than a seeding script.
- [ ] The topbar guest-auth gap noted above, if it turns out to matter before Pages goes live
  as an actual homepage (currently only a problem once `homepage_portal`-equivalent override
  actually points root at Pages — see section 2).
- [ ] `icon/` directory (optional, per `extend.html` §10 — not required, just not done yet).

## 4. `kopling/docs` — mechanism done, 2026-07-22; seed content still open

**Built:** `k-extensions/docs` (registered in root `composer.json`; added
`spatie/yaml-front-matter` as a direct dependency, `league/commonmark` was already vendored
transitively and is now also required directly rather than relied on implicitly).

- `DocPage` model, table `docs_pages` (note: `$table` set explicitly — Eloquent's default
  convention from the class name would have been `doc_pages`, which doesn't match the plan's
  own `docs_pages` naming; caught by a "no such table" failure in the first test run).
- `Extension implements RequestsStorageDriver`: `StorageRequest(id: 'content', access: Private,
  retention: Persistent, permission: ReadOnly)`.
- `PageRegistry` — the one class that touches CommonMark/YamlFrontMatter directly.
  `sync()` resolves the drive via `Resolver`, walks `allFiles()` filtered to `*.md`, skips a
  file whose `sha1` hasn't changed since last sync, and **removes** any `DocPage` whose file no
  longer exists on the drive (an empty drive correctly clears the whole index, not a no-op).
  `tree()` reads `docs_pages` grouped by `section`, ordered.
- `kopling:docs:sync` command — fails loud with a clear "map a drive" message when
  `kopling-docs::content` isn't mapped yet (propagates `Resolver`'s own exception), never a
  silently-empty index.
- Route `GET /{slug}` with `->where('slug', '.*')` (front-matter-derived slugs are hierarchical,
  e.g. `extending/portals` — a plain segment wouldn't match the slash) plus `GET /` showing
  whichever page sorts first in the tree (no separate "index" flag the way Pages has `is_index`
  — the tree's own order already gives a deterministic default).
- Layout reuses `<x-k::community.chrome>` exactly like Style Guide's own layout does.
- `Kopling\Docs\Ux\Sidebar` — a new leaf component (not `Community\Navigation`/`Item`, which
  render a flat Ux-registered list) rendering the section→pages tree into Chrome's generic
  `docs.sidebar-panel` slot.
- No search (v1 scope, matches the plan).
- 15 new tests: `tests/Feature/Docs/PageRegistryTest.php` (front matter parsing, Markdown
  rendering, hierarchical/overridden slugs, change-detection skip, re-render on change, deletion
  cleanup, tree grouping), `PublicDocsTest.php`, `SyncDocsCommandTest.php`. Full suite: 356→371
  passed, same 3 pre-existing unrelated failures.
- Fixed a pre-existing test coupled to "only `kopling/example` declares a storage request" —
  `tests/Feature/Admin/StorageMappingsControllerTest.php`'s eligible-drives test wasn't scoped
  to its own row, so Docs' new (correctly unrestricted) `content` request made a previously-
  excluded fixture drive legitimately show up elsewhere on the same page. Scoped the assertion
  to the `avatars` row specifically rather than the whole page.

**Still open:**
- [ ] Seed content: split `charter.html` §1–11 into one doc-tree section grouping per charter
  section; `charter.html` §12 Decision Log into **one file per entry** under a
  `decision-log/` subpath (the append-only convention becomes "add a file"); `extend.html`
  §1–11 into the "Extending Kopling" section. Real writing work, not mechanism work — same
  treatment as Pages' own open content-migration item.

---

## Open questions (not decided, don't assume)

- Does `kopling-landing` (the repo) become pure seed-content history once the live extensions
  replace it, or stay static indefinitely? This repo's own `CLAUDE.md` currently points at its
  `index.html`/`charter.html`/`extend.html` as canonical — cutover needs those pointers updated
  too, as a deliberate, separate step.
- Decision Log during the transition: keep authoring new entries in `charter.html` until
  cutover, or move to the new per-file Markdown format immediately (risks a temporary dual
  source of truth)?
