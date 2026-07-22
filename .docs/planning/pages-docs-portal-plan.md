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

1. **Storage: `drives` + `storage_mappings` tables, `Resolver`** — foundational, blocks Docs
   entirely, useful independently of this effort. **Backend done as of 2026-07-22** (migrations,
   models, `Resolver`, tests all green) — only the admin UI sub-step (Drives CRUD +
   storage-mappings page in `kopling/admin`) remains open in section 1 below.
2. **Portal path override** — independent of storage, blocks Pages ever owning `/`. Not started.
3. `kopling/pages` — depends on 2 (for homepage eligibility) only. Not started.
4. `kopling/docs` — depends on 1 (storage-backed content, backend piece already in place). Not
   started.

Steps 3 and 4 don't depend on each other and can happen in either order once their own
prerequisite lands. **Next up:** either the storage admin UI (finishes section 1 fully), or move
straight to section 2 (portal path override) since Docs' own build-out doesn't strictly need the
admin UI to exist yet — a `Drive`/`StorageMapping` row can be seeded directly for development.

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
- [ ] Admin UI (can land after the above is solid and tested — not blocking Docs functionally,
  only blocking an admin's ability to configure it without tinkering directly in DB)
  - Drives CRUD — `k-extensions/admin`, same shape as `PeopleController`/`GroupsController`
  - Storage-mappings list — one row per `Manager::storageDrivers()` entry, `Select` (eligible
    drives only — hard-filtered by `supports_public`/`supports_signed`/`writable` against the
    request's declared `StorageAccess`/`StoragePermission`) + `Input` (prefix)

## 2. Portal path override

- [ ] `Portal::$path` — drop `readonly` (mirrors `$id`, which `Manager::portals()` already
  mutates for prefixing) — `k-core/src/Portal/Portal.php`
- [ ] `Portal::$defaultPath` — new readonly property, the declared value, kept alongside the
  now-mutable `$path` so admin UI can show "default vs override" without Manager having
  overwritten the only copy
- [ ] Settings key convention: `core.portal_path.{portal-id}` — override applied as a **final
  map step after `RegistrationCache` retrieval** in `Manager::portals()`, on both the cached and
  live branches — never baked into the cache itself, since the cache is a Composer-boundary
  snapshot and the override is live-editable admin data
- [ ] `admin/portals` — new `PortalsController` + view, same list-with-input-per-row shape as
  the storage-mappings page above; validates path uniqueness across every portal's *current
  effective* path before writing the Setting (fail loud at save time, never guess at resolve
  time)

## 3. `kopling/pages`

New extension, admin-authored pages CMS (replaces the earlier "static `kopling/landing`
extension" idea — rejected because hardcoded marketing copy isn't reusable to other
communities the way an admin-editable builder is).

- [ ] `pages` table — `id` uuid, `path`/slug, `title`, `meta_description`, `published` bool,
  `show_in_nav` bool, `nav_order` int, `is_index` bool (this portal's own root page when no
  slug given — **separate concept** from step 2's portal-level homepage override: that decides
  *which portal* owns `/`, this decides *which page within Pages' own portal* renders at
  Pages' own root)
- [ ] `page_sections` table — `id` uuid, `page_id` FK, `kind` (start with exactly two:
  `rich-text`, `hero` — resist a generic block-type registry until a real page needs a third
  kind), `order` int, `content` json (ProseMirror JSON for `rich-text`), `content_html` text
  nullable (rendered cache), kind-specific fields as a `data` json column (hero's
  subtitle/CTA-label/CTA-url)
  - `rich-text` sections reuse `DocumentRenderer::render()`/`::validate()` and the
    `ValidDocument` rule exactly as `Moment::$body`/`$body_html` does — no second sanitization
    codepath for admin-authored content
  - admin editing UI mounts the same `NotionEditor` component `composer` already uses
- [ ] `Portal(id: 'pages', path: 'pages', canBeHomepage-equivalent via step 2's override,
  permission: null)` — public, no gate
- [ ] Layout: own thin topbar+footer wrapping `<x-k::portal.layout>` directly, **not**
  `Community\Chrome` (chrome is in-app shell; a marketing/static page isn't) — reuses Core's
  shared `UserMenu`/login-register topbar slot so signed-out visitors get real login/register
  CTAs for free
  - nav is **not** Ux-slot-driven — it queries `Page::where('published', true)->where('show_in_nav', true)->orderBy('nav_order')->get()` directly; this is admin-managed DB content, a
    different data source than extension-declared `Ux::add()` entries, don't conflate the two
- [ ] Content migration: rewrite `../kopling-landing/public/index.html`'s sections
  (whatis/principles/stack/extend/governance/founder/support) as seed Pages content, not ported
  verbatim

## 4. `kopling/docs`

- [ ] Depends on step 1 being fully landed (`Resolver`, at minimum the local driver working end
  to end) — Docs declares `RequestsStorageDriver`: one `StorageRequest(id: 'content', access:
  Private, retention: Persistent, permission: ReadOnly)`
- [ ] `docs_pages` index table — `slug`, `title`, `section`, `order`, `locale`, `storage_path`
  (relative path on the resolved disk), `content_hash` (change detection), `content_html`
  (rendered cache), timestamps
- [ ] `spatie/yaml-front-matter` (+ check whether `tiptap-php` already vendors a CommonMark
  version before adding `league/commonmark` fresh) — wrapped behind a `PageRegistry`/`DocPage`
  class; routes/controllers never call CommonMark directly, same "mainstream tool inside,
  sovereign contract outside" discipline as the editor facade
- [ ] `kopling:docs:sync` command — `Resolver::resolve('kopling-docs::content')`, walks
  `allFiles()`, parses front matter, upserts `docs_pages`. Exits with a clear message if
  unmapped (never a silently-empty tree) — same explicit-refresh convention as
  `kopling:extensions:cache`/`RegistrationCache`, no per-request filesystem walk in production
- [ ] Single route `docs/{slug}`, resolved through the DB index, not one route per page
- [ ] Portal reuses `<x-k::community.chrome>` (style-guide already proves this works for a
  non-community, non-composer surface: `:composer-slot="null" :mobile-dock="false"`)
- [ ] Sidebar is a **new** component (`Kopling\Docs\Ux\Sidebar` or similar), not
  `Community\Navigation`/`Item` — that pair renders a flat Ux-registered list, and the docs nav
  is an auto-built section→pages tree from the file scan, a different data shape, placed into
  Chrome's generic `docs.sidebar-panel` slot exactly once, same "one entry into a generic slot"
  pattern Admin/Style Guide already use
- [ ] Search: none for v1 (consistent with search being deferred elsewhere in the settled stack)
- [ ] Content migration:
  - `charter.html` §1–11 → one doc-tree section grouping per charter section
  - `charter.html` §12 Decision Log → **one file per entry** under a `decision-log/` subpath —
    the append-only convention becomes "add a file," a real improvement over editing a growing
    HTML page
  - `extend.html` §1–11 → the "Extending Kopling" section, keeping the existing policy that
    `k-extensions/example` is the reference implementation this content is corrected against

---

## Open questions (not decided, don't assume)

- Does `kopling-landing` (the repo) become pure seed-content history once the live extensions
  replace it, or stay static indefinitely? This repo's own `CLAUDE.md` currently points at its
  `index.html`/`charter.html`/`extend.html` as canonical — cutover needs those pointers updated
  too, as a deliberate, separate step.
- Decision Log during the transition: keep authoring new entries in `charter.html` until
  cutover, or move to the new per-file Markdown format immediately (risks a temporary dual
  source of truth)?
