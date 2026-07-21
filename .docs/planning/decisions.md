# Technical decision history

Engineering-level decision record for this monorepo. This is the technical companion to the
charter's Decision Log (`kopling-landing/public/charter.html`, Section 12): the charter tracks
major, public-facing project decisions in plain language; this file tracks conventions,
contracts, and architectural choices worth remembering — major or minor — in technical detail,
so the *why* isn't lost behind just the *how* a year from now. See `CLAUDE.md` ("Recording
decisions") for what belongs here.

This is a decision log, not a changelog: routine feature work and bug fixes live in commit
history, not here. Entries are dated; append-only applies across sessions/days — a decision
later reversed gets a new entry linking back to the one it replaces. It does not apply within a
single working session: a decision superseded before it ever shipped as the real answer is
corrected in place, not preserved as an abandoned intermediate step.

---

## 2026-07-09 — Root Laravel installation holds no application code

**Decision:** The root installation (`bootstrap/`, root `composer.json`, `.env`) never contains
application code — no `app/`, no `routes/`, no application-level `resources/views`. All Kopling
code lives in `k-core` and `k-extensions/*`, auto-registered via Composer package discovery.

**Why:** Keeps the root usable as an ordinary Laravel app (a developer can still add their own
`app/`, override a route/view) without Kopling's own code fighting that.

**Trade-off accepted:** `Illuminate\Foundation\Application::getNamespace()` throws "Unable to
detect application namespace" (no `app/` psr-4 mapping to find) — cosmetic, CLI-only, not fixed
by adding `app/` back.

**Status:** Decided & implemented.

---

## 2026-07-09 — Source assets live inside the owning package's own domain folder, not a shared root `resources/`

**Decision:** `k-core`'s CSS/JS source lives at `k-core/src/Ux/{css,js}/`, alongside
`k-core/src/Ux/views/` — not in a monorepo-root or package-root `resources/`. The root only
holds the shared Node toolchain; every Vite config points into the owning package's own source
tree and `dist/`.

**Why:** `k-core` is subsplit into its own standalone repo — its assets must travel with its own
tree the same way its PHP/Blade does. Domain-then-kind (`Ux/{views,css,js}`) organizes better
than kind-first as `k-core` grows more domains.

**Status:** Decided & implemented.

---

## 2026-07-09 — k-core ships precompiled CSS/JS as committed release artifacts, compiled only at tag time

**Decision:** `k-core/dist/app.css`/`app.js` are real, git-committed files, produced by
`vite.core-dist.config.js` and committed to `main` only at release time
(`.github/workflows/release.yml`, manual `workflow_dispatch`) — never on every push.

**Why:** Subsplit packages are mirrored files, not builds — splitsh has no build step, so a
Composer install of `kopling/core` needs the compiled assets to already exist as real files
before the split happens. Node/Vite must never run on a live Kopling host; this is what makes
that true. Release-time-only (not every push) avoids bot-commit noise and reuses the existing
`create: tags:` trigger in `subsplit.yml`.

**Gotcha:** Vite's default `publicDir` behavior copies the monorepo's whole `public/` into any
build's `outDir` unless `publicDir: false` is set explicitly — caught once, now set in
`vite.core-dist.config.js`.

**Status:** Decided & implemented. A dual-mode Blade fallback (auto-detect and serve
`k-core/dist` when no monorepo Vite manifest exists) is deliberately deferred — tracked as a
TODO in `CLAUDE.md`; until it lands, `kopling/core` only really works inside this monorepo.

---

## 2026-07-09 — Single-purpose extensions get a flat, unprefixed top-level layout

**Decision:** An extension package organizes flat by kind at its own root: `src/`, `views/`,
`css/`, `js/`, `migrations/`, `routes/` as direct siblings — no `k-` prefix, no
`resources/`/`database/` wrapper.

**Why:** The `k-` prefix at the monorepo root earns its keep by disambiguating Kopling's own
directories from a plain Laravel app's `app/`/`routes/`/`resources/` sharing the same root. That
collision risk doesn't exist once already inside a package folder, so prefixing every subfolder
again is redundant stutter working against "an extension is PHP and templates, full stop."

**Status:** Decided & implemented — `k-extensions/example` is the working reference.

---

## 2026-07-09 — Two-tier decision recording: charter for major/public decisions, this file for full technical detail

**Decision:** Major, project-wide decisions get proposed as a diff to the charter's Decision Log
in plain language. Every decision worth remembering in this codebase additionally gets its own
entry here, in full technical detail.

**Why:** The charter is deliberately plain-language and public-facing — most engineering
rationale (build target layout, directory-naming trade-offs) would be noise to that audience.
This file lets the engineering "why" survive for contributors/agents without diluting the
charter's own purpose.

**Status:** Decided & implemented.

---

## 2026-07-09 — Extension entry point: a plain `AbstractExtension`, discovered by `"type": "kopling-extension"`, directory-convention auto-registration, contracts only for genuinely behavioral capabilities

**Decision:** Every extension ships `src/Extension.php` extending
`Kopling\Core\Extension\AbstractExtension` — no relationship to `ServiceProvider`. Discovery:
`composer.json` declares `"type": "kopling-extension"`; `Manifest` filters `installed.json` by
that type. `Manager` auto-registers whichever of `migrations/`, `views/`, `css/`, `js/`,
`routes/`, `lang/` exist by directory presence alone — no interface required. Contracts
(`Extension\Contract\*`) exist only for capabilities a directory can't express, discovered via
`instanceof`.

**Why:** Wrapping `ServiceProvider` directly would leak Laravel's own API surface into extension
code. A fully plain base class keeps the only things an author touches to `Kopling\Extend\*`/
`Kopling\Core\Extension\*`; directory-convention-over-configuration removes authoring ceremony
for the common cases.

**Coding convention introduced here, applies project-wide:** never mark a method or class
`final` — the override/escape-hatch path only stays real if nothing is ever sealed shut.

**Status:** Decided & implemented. Reference: `k-extensions/example`.

---

## 2026-07-09 — Extension view/translation namespaces include the vendor, not just the package name

**Decision:** An extension's view/translation namespace is the full Composer package name with
`/` → `-` (`kopling/example` → `kopling-example`), not the package name alone with the vendor
stripped.

**Why:** Two vendors can each publish an extension with the same short name (`example`) —
stripping the vendor would let the second-installed one silently collide with the first.

**Status:** Decided & implemented. `Manager::id()` is the single place this derivation happens.

---

## 2026-07-09 — Person/Group are the real Authenticatable model and its group relation, UUID-keyed

**Decision:** `Person` extends `Illuminate\Foundation\Auth\User`, backed by `people`; `Group` is
a plain Eloquent model backed by `groups`, related many-to-many via `group_person`. Both use
`HasUuids`. The `web` guard's model is pointed at `Person::class` via
`config()->set('auth.providers.users.model', ...)` from `ServiceProvider::register()` — root
`config/auth.php` stays untouched.

**Why:** UUID keys match the convention already established elsewhere; setting the model via
`register()` rather than editing `config/auth.php` keeps root config free of application code.

**Status:** Decided & implemented.

---

## 2026-07-09 — htmx auth-wall responses use `HX-Redirect`, via an `ExceptionHandler::renderable()` callback registered from k-core's ServiceProvider

**Decision:** For htmx requests, an unauthenticated response returns 401 + `HX-Redirect` instead
of a normal redirect, via a `renderable()` callback registered from `ServiceProvider::boot()` —
not `bootstrap/app.php`.

**Why:** htmx swaps response HTML into the requesting element; a normal redirect gets swapped in
as a fragment instead of navigating, stranding a login form mid-page. A middleware-based
try/catch was tried first and doesn't work — `Illuminate\Routing\Pipeline` converts route-level
exceptions into a rendered `Response` before they ever propagate back up as a real PHP exception,
so route middleware never observes it; `renderable()` is the actual interception point Laravel
itself uses.

**Status:** Decided & implemented.

---

## 2026-07-10 — `StorageRequest` capabilities: access / retention / permission, backend never named

**Decision:** `StorageRequest` declares a named purpose plus three independent enums:
`StorageAccess` (Private/Public/Signed), `StorageRetention` (Cache/Persistent),
`StoragePermission` (ReadOnly/ReadWrite). Nothing on the class names a backend — that mapping is
an admin-configured choice outside the extension's concern.

**Why:** Access and retention are genuinely orthogonal; conflating them into one "purpose" enum
loses real combinations. `ReadOnly`/`ReadWrite` matters because an extension serving only
vendored assets has no business getting write access to whatever drive it's mapped to.

**Explicitly deferred:** whatever resolves a request to a configured drive must never silently
fall back to a different drive when unmapped/unavailable — would quietly break the app for a
fraction of users on multi-node infra.

**Status:** `StorageRequest`/enums implemented. Admin storage-mapping UI and the
request→drive resolver are not yet built.

---

## 2026-07-10 — Permissions: granular named strings under `Kopling\Core\Authorization`, prefixed by extension id, no hardcoded admin flag

**Decision:** `Permission` is a plain value object (`id`, `label`, `description`, optional
`?\Closure` narrowing condition). Extensions declare theirs via `HasPermissions::permissions()`
writing only the local id; `Manager` prefixes it with the owning extension's id (`::`-joined),
same as view/translation namespaces. No `permissions` table — `Group`/`Person` get
`hasPermission()`/`givePermissionTo()`/`revokePermissionTo()` against a `group_permission`
pivot; the base grant check always runs first, the callback only narrows, never replaces it.

**Why:** Named, granular permissions with groups as assignable bundles is the direct fix for a
real, lived Flarum flaw — a single binary admin flag with no partial access. No permissions
table because a permission's definition lives in code, recomputed fresh each request; only the
grant itself needs to persist.

**Status:** Decided & implemented. No admin UI for assigning permissions yet (PHP API only).

---

## 2026-07-10 — `bootstrap/cache/kopling-extensions.php` and `database/database.sqlite` are fixed by composer hooks, not documented as manual steps

**Decision:** A new `kopling:extensions:discover` Artisan command (mirroring Laravel's own
`package:discover`) rebuilds the extension manifest cache; root `composer.json`'s
`post-autoload-dump` runs it plus a one-liner creating `database/database.sqlite` if missing —
both before either gap could be documented as a manual step.

**Why:** Both gaps were being worked around by hand repeatedly; the first instinct was to write
"remember to do this" into `CLAUDE.md` rather than ask whether to just close the gap. Both are
cheap, standard fixes.

**Status:** Decided & implemented.

---

## 2026-07-10 — Portals: named UI surfaces declared through `Core`, an `AbstractExtension`; Manager always loads first; never a second gating mechanism

**Decision:** `Portal` is a plain VO (`id`, `label`, `path`, `layout`). `HasPortals::portals()`
mirrors `HasPermissions`. Core's own permissions/portals are declared through a new `Core` class
— `extends AbstractExtension implements HasPermissions, HasPortals` — always prepended first by
`Manager::extensions()`, running through the identical collector loops as any real extension; no
special-cased merge anywhere.

**Why:** Core previously hand-wrote fully-prefixed permission ids as a special case. Making
`Core` itself an `AbstractExtension` implementor removes that asymmetry structurally — one way
to declare a permission or portal, one place that does the prefixing, regardless of declarer.
Portal is explicitly **not** a gating mechanism — no `canAccessPortal()` exists; routes gate
themselves with ordinary `can:` middleware, the direct structural fix for the same
hardcoded-admin-flag problem the permissions decision above addresses.

**Status:** Decided & implemented. Two portals exist: `core::community`, `core::admin`.

---

## 2026-07-10 — `ChangesUx`/`Ux`: one contract for every UI extension point, not one per surface

**Decision:** `ChangesUx::ux(): Ux` is the single contract for placing UI into any named slot.
`Extend\Ux` is a fluent builder (`Ux::make()->add($component, $data)->in($slot)->after($id)
->before($id)->as($id)->when($condition)`) mirroring `Route::get()->name()->middleware()`
chaining. `$slot` is a fully-qualified string, never auto-prefixed (a public rendezvous point);
`$id`/`$condition` are prefixed by `Manager`, same as permission ids. `SlotResolver` filters to
a slot, best-effort positions via `after`/`before` (a missing anchor silently no-ops — "outlets
compose; overrides don't"), then filters by `condition`.

**Also decided the same session, same mechanism:** every declared value object (`Permission`,
`Portal`, `UxEntry`, `StorageRequest`) standardized on a mutable `$id` field, prefixed by
`Manager` in place rather than reconstructed — these objects are recomputed fresh every request,
so mutation has no cross-request safety cost. `Ux` later gained `edit()` (re-select an
earlier-in-chain entry), `replace()`/`remove()` (target another entry by its already-qualified
id, tagged via a `UxAction` enum), and `add()`/`replace()` accepting a component's own FQCN
(resolved back to its registered Blade tag via `ComponentTag::resolve()`), rather than a tag
string only.

**Why one contract instead of one per surface:** `HasPermissions`/`RequestsStorageDriver` already
showed the value-object+contract+collector pattern doesn't stay cheap past two instances; a
generic slot mechanism carries per-surface specifics (slot, ordering, condition) as data instead
of new methods per surface.

**Status:** Decided & implemented. `Core::ux()` is the reference; `k-extensions/example` proves
cross-extension anchoring and gating.

---

## 2026-07-10 — Core Ux components: PHP class path mirrors Blade view path, domain-nested; extensions stay flat

**Decision:** A core-owned `<x-k::*>` component's PHP class path mirrors its Blade view path
1:1, domain-nested under `Ux/` (e.g. `Ux/Portal/Navigation/Side.php` ↔
`views/portal/navigation/side.blade.php`). Extension components (if any) stay flat/simple —
never required to adopt this nesting.

**Why:** Core is expected to grow many components across several UI domains — a flat bucket
doesn't scale legibly the way domain-then-kind organization already proved for CSS/JS. Extensions
don't get the requirement because they're single-purpose and flat by design.

**Status:** Decided & implemented.

---

## 2026-07-10 — `Ux\Portal\Layout`/`Slot`: each Portal's layout defines its own slot map; the shared shell holds only html/head/body

**Decision:** `Portal\Slot` is a generic `<x-k::portal.slot name="...">` resolving any named
slot with no opinion about surrounding markup; `Portal\Layout` is stripped to just the universal
html/head/body wrapper. Each Portal's own layout view composes its own header/nav/regions using
`Slot`.

**Why:** Admin's simple shape and Community's richer, multi-region shape are genuinely different
layouts, not the same layout with different content — a shared component with one fixed slot map
would force a special case onto one or the other. A third Portal can define a third slot map with
zero changes to `Slot`/`Layout`/`Manager`.

**Status:** Decided & implemented.

---

## 2026-07-10 — Admin Portal split into its own extension (`kopling/admin`); Core keeps only Community

**Decision:** The Admin Portal, its permission, and layout moved out of `Core` into a new,
ordinary Composer-discovered extension, `k-extensions/admin` — nothing about being "the admin
panel" gets special-cased.

**Real bug found and fixed while doing this:** `Manager::portals()` prefixed `Portal::$id` but
never `Portal::$permission` — the Admin portal's own gate had never actually been passable since
that field was added. Fixed by prefixing it the same way.

**Flagged, not solved:** extension load order beyond "Core first" is uncontrolled — moot while
Admin lived inside Core (always first), a real gap once split out. Resolved later, see
2026-07-12.

**Status:** Decided & implemented for the split; load order deferred.

---

## 2026-07-10 — Runtime theme overrides: a `ChangesTheme` contract, `theme_tokens` for ad-hoc edits, no selection/editor yet

**Decision:** Since Node/Vite never runs on a live host, runtime theming means overriding a
sparse, curated set of CSS custom properties (`Theme\Token`, a finite enum) the one compiled
daisyUI theme already defines, via an inline `<style>` layered on top. `ChangesTheme::theme()`
lets an extension ship a named theme; a `theme_tokens` table holds ad-hoc per-token overrides on
top of whatever's installed. `Theme::css()` merges both and renders
`:root[data-theme="kopling"] {...}` — high enough specificity to reliably beat the compiled rule.

**Two trust levels:** `ChangesTheme` values are validated once and throw on failure (an author's
own bug, fail fast). `theme_tokens` rows are validated on every read and silently skipped on
failure — a bad admin-submitted row must never take the whole site down.

**Status:** Decided & implemented for the non-interactive half. Admin editor and theme selection
among multiple installed themes not yet built.

---

## 2026-07-10 — Flagged for later: every discovered extension is always enabled, no disable toggle exists

**Status quo, confirmed deliberate for now:** every Composer-discovered extension is treated as
active unconditionally. Fine today — installed already implies wanted. `CannotBeDisabled` exists
specifically anticipating a future toggle; the toggle itself is out of scope.

**Status:** Superseded 2026-07-21 — a real enable/disable toggle exists now. See below.

---

## 2026-07-10 — Community's card feed extracted into granular `Ux/Card/*` components; header/body/footer made genuinely extensible, bound to a real `Moment` model

**Decision:** Hand-written card markup became eleven small components under `Ux\Card\*`
(`Card`/`Top`/`Body`/`Footer`/`Avatar`/`Author`/`Timestamp`/`Control`/generic `Row`/`Column`).
`Top`/`Body`/`Footer` each expose a `SLOT` + `defaults(Ux $ux)`, composed thinly from `Core::ux()`
— the actual child-registrations live on each component class, not centralized. A new
`Ux\Context` (`subject`, `actor`) is built once per rendered `Moment` and threaded through every
slot-rendered leaf; every leaf takes both `array $data` (static, author-declared) and
`?Context $context` (the dynamic binding) as two separate channels.

**Why this decomposition, not fewer/coarser components:** matches the charter's stated
extensibility model directly — partial granularity is the extensibility budget. A monolithic
`Card` would force forking the whole thing for any small variation.

**Explicitly dropped:** a third mechanism for wholesale component replacement
(`ReplacesComponents` + a component registry) was designed and rejected on review — `Card` stays
directly tagged; only what's inside it is extensible.

**Status:** Decided & implemented. `Footer` ships with zero default children on purpose — the
real consumer (reactions) didn't exist yet, and a placeholder count would be exactly the fake
data this redesign was meant to remove.

---

## 2026-07-10 — Community index goes live: htmx polling first, SSE-over-FPM/Reverb deliberately not built yet

**Decision:** New Moments appear without a manual reload via plain htmx polling (`hx-trigger=
"every 12s"`), not SSE or Reverb — an idle poll returns bare `204`; finding something new returns
a "N new — click to view" banner via `HX-Reswap`, loaded on click through the same partial the
initial page render uses.

**Why polling, not SSE:** this install's actual hosting profile (`QUEUE_CONNECTION=sync`, no
Redis/Reverb/broadcasting) is exactly the shared-hosting tier the project's own stated posture
names. SSE-over-FPM holds one PHP-FPM worker open per open tab — real risk on that tier; polling
holds nothing open and needs zero server config.

**Why a click-to-load banner, not auto-inserted cards:** silently shoving new content above what
someone's reading is jarring and cuts against the project's stated anti-engagement-bait values.

**Status:** Decided & implemented. SSE/Reverb are known upgrade paths, deliberately not pursued
without a concurrency plan.

---

## 2026-07-11 — Login/registration is core-owned scaffolding + two extension points (`ValidateLogin`/`AttemptLogin`, `ValidateRegistration`/`AttemptRegistration`); no login method built in

**Decision:** `LoginController`/`RegistrationController` own the routes but know nothing about
what "credentials" means. Each dispatches a pure veto event (`Validate*`, via `$events->until()`)
then a mutable outcome event (`Attempt*`: `?Person $person`, a `ValidationException`, fluent
`succeeded()`/`failed()`) via plain `dispatch()`, holding a local reference rather than trusting
`until()`'s return value (verified via tinker: `until()` returns `null` the moment nothing
listens, which is true today).

**The one real difference for registration:** `AttemptRegistration::succeeded()` can carry an
*unsaved* `Person` — `register()` saves it exactly once, after every listener has run, so a
later-registered listener can still mutate the same instance before the one write happens.

**Why the throttle key dropped the identifier field:** assuming every login method's form calls
its identifier `email` is exactly the assumption this event-pair design exists to avoid; keyed
by IP alone instead.

**Trade-off accepted:** no login-method extension exists yet, so every attempt fails with an
empty-message `ValidationException` until `kopling/auth-email-password` lands (same day, later).

**Status:** Decided & implemented for the scaffolding.

---

## 2026-07-11 — Core's views moved to `k-core/views/`; Manager package key changed from the literal `'core'` to its real Composer name `'kopling/core'`, namespace now `kopling-core::`

**Decision:** `k-core/src/Ux/views/` → `k-core/views/`, matching the directory convention every
extension already follows — Core's views now load through the same per-package loop, no
hardcoded `loadViewsFrom` call. Core's key in `Manager::extensions()` changed from a hand-picked
`'core'` literal to its real Composer name, so `Manager::id()` derives `kopling-core::` the same
way it derives any extension's namespace, instead of a bespoke unprefixed one.

**Why:** Routing views through the shared convention-based loop only works cleanly if Core's own
key resolves the same way an extension's does — leaving `'core'` as a special case would have
meant a second parallel `loadViewsFrom` call just for Core.

**Status:** Decided & implemented. `k-core/migrations` and `routes/web.php` are (harmlessly)
loaded twice — once by an explicit call, once by the same per-package loop — pre-existing, not
introduced here, left as-is.

---

## 2026-07-12 — Extension load order: contract-dispatched rules, not a numeric priority

**Decision:** Two contracts resolve load order: `HasLoadOrder` (later split into `LoadsAfter`/
`LoadsBefore`, 2026-07-15) for explicit, self-declared constraints by package name; and
`InfluencesLoadOrder::loadOrderRules(): array<class-string, Directive>` for constraints placed on
*other* extensions by capability contract rather than package name — the extension owning a
contract declares "implementors of this load after me" once, matched via `instanceof` against
whichever installed extensions happen to implement it, present or future. Explicit `HasLoadOrder`
always wins over an inferred rule for the same pair. `Resolver::sort()` is Kahn's algorithm;
unrelated extensions fall back to deterministic alphabetical order. A genuine cycle throws.

**Why not a numeric priority:** a flat int scale is a single global namespace every extension
author has to reason about collectively, degrades as more extensions land between two values, and
can't express "I need to come after X specifically" — only a rough position in a line.

**Status:** Decided & implemented. Solves Admin's real, already-flagged "who loads first" gap
without hardcoding "Admin loads first" anywhere.

---

## 2026-07-12 — Routes (and css/js) attach to a Portal via `ExtendsPortals`, not a directory convention

**Decision:** `ExtendsPortals::extendsPortals(): array<PortalExtension>` is now the only way
anything attaches to a Portal's route group — including the extension that declared the Portal.
`PortalExtension` targets a Portal by its fully-qualified id (author-written, never
auto-prefixed) and offers `->routes()`/`->css()`/`->js()`.

**Why:** Two real, already-hit bugs: discussions' routes lived entirely outside any Portal (no
`InjectPortal` resolution, forcing a workaround elsewhere); `kopling/admin`'s Portal never called
`->routes()`, silently registering zero routes with no error at all
(`Arr::wrap(null) === []` swallowed it). One mechanism for owner and non-owner alike makes both
bug classes structurally harder to reintroduce.

**Asset serving:** css/js files live in package directories, not `public/`. Rather than a
`{package}/{path}` route (a path-traversal hazard), `Manager::extensionAssets()` builds a flat
registry keyed by a hash of every already-validated path; a request can only ever resolve to a
known-safe path or nothing.

**Status:** Decided & implemented.

---

## 2026-07-13 — Navigation split out of Sidebar into its own slot; nav-item rendering (menu vs. mobile dock) decided at the render call site, not at registration

**Decision:** `Community\Navigation` (a new, nav-only slot) replaced `Sidebar`'s own default nav
link — `Sidebar` had mixed nav links and free-form widget cards in one slot, which also meant
widgets' `<div>` blocks sat inside an invalid `<ul>`. `Item` gained a `$surface` prop
(`'menu'`/`'dock'`) switching which markup it renders — deliberately **not** part of `UxEntry`'s
static `$data`, since which surface an entry renders into is a render-time layout decision, not
something the registering extension should own. The dock and the sidebar menu are two independent
`SlotResolver::resolve()` calls against the same registrations.

**Why not `UxEntry::$data`:** would make every registering extension responsible for knowing
about layout variants that don't exist yet at registration time — the same coupling the
`$context`/`$data` split already exists to avoid.

**Status:** Decided & implemented.

---

## 2026-07-14 — `Extend\Model::linksTo()`: a Moment card's detail-page link is a declared, native cascade, not a template override

**Decision:** `Extend\Model::linksTo(string $route, array|callable $parameters = [], bool|
callable $when = true)` lets an extension declare that a model's card should link to a given
route — mirroring `relation()`'s existing `$when` contract/shape rather than inventing a second
"conditional declaration" pattern. `Ux\Context::getSubjectUrl()` resolves it at render time;
`Card\Content` wraps the title in `<a>` only when non-null. `discussions` adds one line to its
existing model declaration; no Blade override needed.

**Why native, not an override:** explicitly requested — Core's own default rendering should know
how to link out when an extension has declared it should, the same way it already knows how to
read `title`/`body` off the subject.

**Status:** Decided & implemented. Collision rule: last-registered wins, same as colliding cast
keys.

---

## 2026-07-14 — Card `Control` becomes a real, slot-driven dropdown menu; new generic `Ux\Dropdown` primitive

**Decision:** `Control` gained its own slot (`Control::SLOT`), resolved like `Top`/`Footer`. A
new generic `Ux\Dropdown` (trigger slot + default slot, Popover API + CSS anchor positioning, no
JS) supplies the actual menu markup, decoupled from `SlotResolver` so it's reusable by anything
wanting a dropdown, not just cards.

**Why not a standalone button next to Control instead (no core change):** rejected explicitly —
"we build to get to the right state of the software, doing a temporary workaround is never good
enough."

**Status:** Decided & implemented.

---

## 2026-07-14 — Admin settings framework: `HasAdminSettings`, `Ux\Form\*`, flat `settings` key/value store

**Decision:** `HasAdminSettings::adminSettings(): array<Ux\Form\Field>` — deliberately not named
`HasSettings`, freeing that name for a future per-person preferences contract. `Field` declares
what a setting is (id/label/description/default/component); Admin (the extension that owns the
concern) decides persistence and placement, same split `StorageRequest` established for storage.
Persisted in a flat `settings` table via a plain `DB::table()` helper, not an Eloquent model — no
relation/cast to earn one. One page-level Save, not per-field htmx autosave (explicit choice,
weighing simplicity over reactions'-style responsiveness).

**Why a dedicated contract instead of routing through `ChangesUx`:** wanted a distinct,
greppable capability mirroring how `permissions()`/`portals()`/`storage()` are each their own
contract, not everything funneling through the generic Ux mechanism.

**Status:** Decided & implemented. Field declarations for real extensions came later, framework
only.

---

## 2026-07-14 — Admin's chrome (sidebar + rail) scaffolded via the existing generic `Portal\Slot`; `Community\Navigation` deliberately not made Portal-aware

**Decision:** Investigating a shared-nav-component question surfaced a real, live bug: `UxEntry`/
`SlotResolver` carry no Portal-scoping concept at all — a slot is only ever a string, so
`example`'s illustrative nav item and Admin's real Settings link both silently targeted the exact
same slot string, isolated only by coincidence of which layout rendered which name. Fixed by
retargeting both to distinct, portal-owned slot names (`kopling-admin::admin.navigation`, etc.),
not by adding real structural Portal-scoping — deferred until a second forcing example exists.

**Why `Community\Navigation` itself isn't generalized:** every existing region component is one
class = one hardcoded slot; no precedent for a region parameterized at runtime by which Portal it
belongs to, and `Navigation`'s content (dual menu/dock surfaces, hardcoded Home route) is
Community-specific top to bottom anyway.

**Status:** Decided & implemented. Real Portal-scoping tracked in `roadmap.md`, not built.

---

## 2026-07-14 — `Authorization\Permission` split: the declarative value object moved to `Extend`, the name freed for a real Eloquent model over `group_permission`

**Decision:** The existing declarative value object (what `HasPermissions::permissions()`
returns) moved to `Extend\Permission` — the namespace `Extend\Model`/`Extend\Ux` already occupy
for "what an extension declares." `Authorization\Permission` now names a real Eloquent model over
`group_permission`; `Group::hasPermission()`/`givePermissionTo()`/`revokePermissionTo()` moved
off raw `DB::table()` onto a real `HasMany` relation.

**What this model is not:** a catalog of every permission that exists — a permission's
label/description/callback still lives entirely in code, recomputed fresh each request; this
model only replaces the raw-SQL grant-row queries.

**Trade-off accepted:** `givePermissionTo()` moved from an atomic DB-level insert-or-ignore to a
check-then-create (`firstOrCreate`) — a narrow race window, accepted since permission grants are
low-frequency, admin-initiated writes, not a hot concurrent path.

**Status:** Decided & implemented. `Person::hasPermission()`'s own raw-SQL join was explicitly
left out of scope.

---

## 2026-07-15 — Dropped closures from UxEntry/Permission; added `Guest`; flatfile cache for Manager's aggregations

**Decision:** `UxEntry::$condition` and `Extend\Permission::$callback` no longer accept a
`\Closure` — permission ids (strings) only, so every declared entry stays plain, cacheable data.
A new `Guest extends Person` (never persisted, `hasPermission()` hard-`false`) substitutes for
`null` in Gate checks; `Permission::$allowsGuests` grants a permission to Guest specifically,
exclusive of a real Person's own Group grants (a real person is never treated as a guest even if
their Group happens to hold an `allowsGuests` permission).

**Why closures had to go:** the new flatfile cache (`RegistrationCache`,
`bootstrap/cache/kopling-registrations.php`) for every deterministic `Manager` aggregation
(`permissions()`/`portals()`/`ux()`/etc.) needs each value object to round-trip through
`toArray()`/`fromArray()` — a closure can't serialize. Explicit-only (`kopling:extensions:cache`),
no automatic trigger, since editing an extension's `ux()` isn't a Composer operation an automatic
hook could hang off of.

**Status:** Decided & implemented. Broke and fixed two real usages (composer's signed-in check
moved into its own view; auth-email-password's guest-only links now use a `kopling-core::guest`
permission).

---

## 2026-07-15 — `Extend\Model::creating()`/`saving()`: model lifecycle hooks for extensions

**Decision:** Two nullable-`Closure` properties on `Extend\Model`, applied via the target
model's native Eloquent `creating()`/`saving()` statics — lets an extension inject a column value
at creation or transform an attribute on save without the target model knowing about it. Multiple
extensions targeting the same model each get their own hook; both fire, no collision rule needed
(Eloquent supports multiple listeners per event natively).

**Why reuse `Extend\Model` rather than the casts mechanism's own static-registry approach:**
Eloquent's `creating`/`saving` statics work on any Eloquent model with zero base-class opt-in —
the casts mechanism only took effect for a model extending Core's own base class, which (at the
time) no real model did (see 2026-07-18, "`Extend\Model::cast()` was dead").

**Status:** Decided & implemented.

---

## 2026-07-15 — `HasLoadOrder` split into `LoadsAfter`/`LoadsBefore`

**Decision:** Supersedes the `HasLoadOrder` half of the 2026-07-12 load-order decision.
`HasLoadOrder` was the only contract in the codebase forcing an implementor who cared about only
one direction to still declare a no-op for the other. Split into two independently-opt-in
single-method interfaces.

**Status:** Decided & implemented.

---

## 2026-07-15 — Icon extensibility: Blade Icons + a semantic `HasIcons`/`ChangesIcons` layer, Font Awesome as the baseline

**Decision:** `blade-ui-kit/blade-icons` (Font Awesome free tier bundled) replaces hand-authored
inline SVG. `HasIcons::icons()` declares a semantic id + mandatory Font Awesome default; an
icon-pack extension implements `ChangesIcons::iconMap()` to remap already-declared ids to its own
icon names, tolerantly (an unrecognized id is left alone, not validated against a closed enum —
icon names are open and extension-owned).

**Why not letting extensions reference pack-prefixed tags directly:** Blade Icons has no "active
pack" concept — a tag is permanently bound to one installed set, so swapping the site's icon pack
would mean editing every extension by hand, repeating Flarum's own well-known icon problem.

**Status:** Decided & implemented. No admin picker UI yet (needs a `Select` form field, which
didn't exist until 2026-07-16).

---

## 2026-07-15 — `<x-k::modal>`: native `<dialog>`, not Popover API, for form-bearing dialogs

**Decision:** `Ux\Modal` (same trigger/default-slot shape as `Dropdown`) is built on the native
`<dialog>` element rather than Dropdown's Popover-API approach.

**Why:** A form-bearing modal needs real focus-trapping (inert background, focus trap, Escape
closes) — native to `<dialog>`'s `showModal()`, deliberately not provided by the Popover API.

**Status:** Decided & implemented. Later gained an optional explicit `$id` (2026-07-18) so a
validation-error redirect-back can reopen the exact dialog that failed, and a self-reopening
mechanism generalized from a tags-specific version (2026-07-19) — any instance whose `$id`
matches a round-tripped `_form` hidden input reopens itself, no page-level script needed.

---

## 2026-07-15 — People/Groups admin UI: reuses the already-declared `manage-people` permission, no new migration

**Decision:** New `PeopleController`/`GroupsController` in `k-extensions/admin`, plain full-page
POST+redirect forms gated by the existing, previously-unused `manage-people` permission. Group
assignment uses a new generic `Ux\Form\MultiSelect` (a checkbox-list picker, not Group-specific)
inside `<x-k::modal>`. No new migration — `group_person` already supports add/remove with no
history needed.

**Status:** Decided & implemented.

---

## 2026-07-16 — `QueryingMoments`/`RenderingCard`: feed-reorder and card-styling extension points reuse the existing `ListensToEvents` mechanism, not a new contract

**Decision:** Two new Core events with mutable public state a listener acts on:
`Content\Event\QueryingMoments` (carries the query `Builder`, dispatched right before it runs)
and `Ux\Card\Event\RenderingCard` (carries `Context` + an accumulating class list). Both reuse
`ListensToEvents`/`Manager::listeners()` — already real, tested infrastructure — rather than a
new `Extend\Model`-style hook mechanism.

**Why:** Direct call during planning: the feed query and the card's outer wrapper had no
extension point at all (`Card`'s own docblock stated the wrapper was sealed), and the existing
event-listener pattern already generalizes to both without new machinery.

**Status:** Decided & implemented. First real consumer: Pin's feed-reorder and reason-colored
border (2026-07-16, below).

---

## 2026-07-16 — Pin extension: `k-extensions/pin`

**Decision:** `kopling/pin` — pin a Moment with a reason, a reason-mapped color, optional Group
targeting. `pins.moment_id` unique (one active pin, re-pin via `updateOrCreate`, no history);
`group_pin` pivot (empty = visible to everyone). One flat permission gates both the UI entry and
the controller — no per-instance/ownership policy exists in this codebase to build on instead.

**Also fixed, found along the way:** a plain (non-htmx) POST to an `auth`-gated route while
logged out crashed with `RouteNotFoundException` — `Authenticate::redirectTo()` calls
`route('login')` directly, and only a namespaced login route exists. Fixed via
`Authenticate::redirectUsing()`, called from `ServiceProvider::boot()` — **not**
`bootstrap/app.php`'s `redirectGuestsTo()`, which would have violated the standing "root holds
no application code" rule. First attempt used `bootstrap/app.php` and was reverted for exactly
that reason.

**Status:** Decided & implemented.

---

## 2026-07-17 — TipTap 3.x rich-text editor (v1: a Notion-styled editor, not the paid template)

**Decision:** `Moment`/`Reply.body` holds canonical ProseMirror JSON; a new `body_html` column
holds sanitized HTML rendered server-side at write time by a hand-written tree-walker over a
closed, PHP-declared node/mark catalog (`Ux\Editor\EditorNode`) — not an HTML sanitizer over
client-supplied markup. A new `ChangesEditor` contract lets extensions vote which of a closed set
of nodes is enabled (mirrors `ChangesTheme`'s "vote into a closed catalog" shape); a genuinely
alternative editor implementation is deferred as v2 (needs real per-extension JS bundling, which
doesn't exist yet).

**Scope correction:** the originally-requested Tiptap Notion-like template turned out not to be
open-source (paid plan, React-only) — built a Notion-*styled* editor instead from free/MIT
primitives only, each license individually verified before adding.

**Status:** Decided & implemented. Established the dynamic-`import()` shim pattern (a tiny
always-loaded loader, the real payload only imported once a mount point exists) reused by later
heavy-JS components (EmojiPicker, TagInput, IconPicker).

---

## 2026-07-18 — Upvotes: per-tag vote-emoji config, reusing `reactions`, `PALETTE` untouched

**Decision:** Rejected the roadmap's literal suggestion (add 👎 to `Reaction::PALETTE`, which
renders unconditionally on every card) — instead each `Tag` gets nullable `upvote_emoji`/
`downvote_emoji`; votes are ordinary rows in the existing `reactions` table, validated against
the moment's own tags' configured emoji, not `PALETTE`. A `vote` component renders before the
generic `rail`, which excludes any vote-claimed emoji so the same emoji never gets two buttons. A
`?sort=top` feed mode (thumbs-up count only, not net score) reuses the same `QueryingMoments`
listener mechanism as Pin's feed-reorder.

**Status:** Decided & implemented. Required Tags' first admin CRUD as a side effect (no admin UI
existed for tags at all before this).

---

## 2026-07-18 — `Extend\Model::cast()` was dead for every real model; all real models now extend `Database\Model`

**Decision:** `Database\Model::getCasts()` (the override reading `Manager::models()`'s
registered casts) only takes effect for a class extending `Database\Model` — and no real model
did; only a test fixture proved the mechanism worked in isolation. Fixed: all eight real models
now extend `Database\Model`. `Person` can't (must extend `Authenticatable`) — the override was
extracted into a trait, `Database\Concerns\HasExtendedCasts`, reading/writing
`Database\Model::$extendedCasts` by explicit class reference (never `static::`, since a trait
gives each *consuming* class its own independent copy of a property it declares — would have
silently given `Person` a permanently-empty registry otherwise).

**Why worth its own entry:** found while designing a fillable-extension mechanism that would have
inherited the identical, silently-inert gap — a mechanism that looks tested (a passing fixture
test) but was never actually wired to anything real in production.

**Status:** Decided & implemented.

---

## 2026-07-18 — Moved upvote/downvote ownership from `tags` to `reactions`

**Decision:** The same-day Upvotes implementation put `upvote_emoji`/`downvote_emoji` schema,
validation, and admin form fields directly in `k-extensions/tags`, purely because tags already
had a table/CRUD to bolt onto — despite the roadmap's own wording naming reactions as the owner.
Caught when asked where it hooks into tags: it didn't hook in, it just lived there. Moved to
`reactions` via two new, genuinely generic mechanisms rather than special-casing this one pair of
columns: a new `ValidatesModels` contract (`modelValidationRules(): array<class-string,
array{rules, messages}>`, aggregated by `Manager`, so an extension can contribute validation
rules for a model it doesn't own) and an optional `:context` prop on `Ux\Portal\Slot` (was
page-level-only before this).

**Why this is the canonical example of the ownership rule:** full write-up in
`feedback-extension-ownership-boundaries` (agent memory) — a feature belongs to the extension
whose domain concept it is, never to whichever extension has convenient CRUD already built.

**Status:** Decided & implemented. Tags' admin list table lost its upvote/downvote columns as a
result (a real, visible regression — a proper "list column" extension point would fix it, not
attempted here) — a materially different, per-row extensibility problem than the form-field one
this refactor solved.

---

## 2026-07-19 — `Extend\Model` gained a `saved()` hook, alongside `creating()`/`saving()`

**Decision:** `creating()`/`saving()` both fire pre-write, so neither can support a many-to-many
sync that needs the owning row's real primary key. `saved()` fills that gap — used by `tags` to
sync `moment_tag` from the request after a `Moment` is saved, guarded on `request()->has('tags')`
(not a default-to-empty read) since `saved()` fires on *every* save of a Moment, and defaulting a
missing key to `[]` would silently strip an unrelated save's tags.

**Status:** Decided & implemented. Second real consumer (after `creating`/`saving`) proving the
mechanism generalizes.

---

## 2026-07-19 — `Ux/Form/TagInput`, built on `@yaireo/tagify`, is the tag-picker widget

**Decision:** A same-day hand-rolled `Ux/Form/Combobox` (htmx-driven search + inline Alpine pill
state) was rejected on review in favor of `TagInput`, built on the mature, MIT-licensed
`@yaireo/tagify` (real keyboard nav/ARIA/edit-in-place included, closing the hand-rolled version's
own admitted "no keyboard nav" gap). Core owns the widget end to end; the caller supplies a
`searchUrl` returning `{id, label}` JSON pairs, following Tagify's documented async-whitelist
pattern. Since Tagify serializes its selection as one JSON string rather than a plain array, the
JS shim keeps a set of real hidden `name="{name}[]"` inputs in sync on every add/remove — so the
server-side contract (`request()->input('tags', [])`) never had to change.

**Why over Tom Select (the other real MIT/Apache candidate):** Tagify's "mixed tags" mode leaves
room for a future inline `@mention`/`#tag` feature to reuse the same dependency later.

**Status:** Decided & implemented.

---

## 2026-07-19 — `Ux::add()` id collisions now throw, instead of silently overwriting

**Decision:** `Manager::applyUxAdd()` throws `\LogicException` when a second `Add` resolves to an
id an earlier `Add` in the same registry already used, instead of the second silently replacing
the first's entire registration (including its slot).

**Why:** A real bug shipped invisibly this way — two of `tags`' own `->add()` calls both landed
on the same local id, so the second (an admin-nav item) silently overwrote the first (a card
badge), which stopped rendering with no error anywhere. Traced from a confusing bug report
("tags don't show on the card") because persistence was fine the whole time — this was purely a
UI-registration collision. A legitimate `replace()`/`remove()` targeting an existing id is
unaffected; only two `Add`s on the same id trip the guard.

**Status:** Decided & implemented.

---

## 2026-07-19 — Extensions follow core's daisyUI theme; hand-rolled CSS is the exception, and even then must consume core's theme variables

**Decision:** Extensions style themselves through core's `<x-k::*>` components and theme tokens.
Custom CSS is the exception (e.g. wrapping a third-party widget with no core equivalent), and even
then must read core's daisyUI CSS variables rather than hardcoding values.

**Why:** Formalizes what `extend.html`'s safelisted-utilities rule already implied. Hardcoded
colors silently break runtime re-theming and fragment the visual feel as more extensions ship.

**Status:** Decided; enforced on core's side via `ComponentCoverageTest` (fails CI the moment a
new core component ships without a style-guide showcase entry). Extension-side enforcement (a
lint checking an extension's CSS only references core's variables) remains unbuilt.

---

## 2026-07-19 — `Ux::first()`: pins an entry to the front of its slot outright, no anchor id required

**Decision:** `after()`/`before()` only position relative to another entry's id, which can't
express "this must lead, full stop" when nothing else in the slot is guaranteed to exist. Added
alongside the topbar user-menu dropdown, whose admin-panel link needed to always lead regardless
of extension load/discovery order.

**Status:** Decided & implemented.

---

## 2026-07-19 — Reply cards reuse Core's Card/Top/Body/Footer via a slot override, not a duplicate set

**Decision:** `Card\Top`/`Body`/`Footer` (and `Card` itself) gained an optional `?string $slot`
override, falling back to each class's own `SLOT` constant when omitted. Discussions' `Reply`
uses this to get the same extensible avatar/author/timestamp+body+footer shape under its own
distinct slot family (`kopling-discussions::reply.*`) — deliberately not Core's own Moment slots,
since sharing them would mean every Moment-only registration (vote/rail, teaser/engage) renders
on a reply too.

**Why generalize rather than duplicate:** none of `Top`/`Body`/`Footer` ever read anything
Moment-specific themselves — only their `defaults()` did — so making the slot itself overridable
is less code and less drift risk than a hand-copied parallel set kept in sync by hand forever.

**Status:** Decided & implemented.

---

## 2026-07-19 — Community/Admin/Style Guide unified onto one shared chrome

**Decision:** Community, Admin, and Style Guide had each hand-rolled their own navbar/sidebar/
rail layout — copies of each other at first that quietly drifted (different widths/behaviors, the
same layout bug fixed three separate times as each was touched independently). `Community\Chrome`
is now the one shared shell all three reuse, with every region a constructor param defaulting to
Community's own exact values.

**Why:** Three copies of the same shell drift by construction — any real inconsistency has no
single place to reconcile from, and every future fix has to be found and applied three times
instead of once.

**Status:** Decided & implemented.

---

## 2026-07-20 — Card visual redesign: sectioned divided-panel shape, title moved into the header row, badges float over the card's top edge, footer is one no-wrap scrollable row

**Decision:** `Card` dropped daisyUI's single `card-body` region for three independently-padded
sections (`Top`/`Body`/`Footer`) separated by `divide-y`, with `outline` replacing `card-border`.
A Moment's title moved out of `Body` into a new `Card\Title` leaf registered first in `Top`, given
`flex-1` so it fills whatever space the row's other (fixed-width) entries don't need — packing
avatar/author/timestamp/control against the row's right edge as a side effect, with no layout
opinion added to those generic, reused-elsewhere leaves. Tag badges render as a floating strip
(`Card\Badges`, a new component, absolutely positioned straddling the card's own top edge) — an
intermediate approach (a second in-header row, `Top::SECONDARY_SLOT`) was built and removed the
same session once the actual ask ("floating on the edge of the card") was clarified; `Badges`
required splitting the card's outer/inner div structure so `overflow-hidden` (needed to clip
`Top`/`Body`/`Footer`'s corners) doesn't also clip the floating strip escaping above it. `Footer`
is now `flex-nowrap overflow-x-auto` (always one row, never wraps to a second line); reactions'
rail skips any emoji with a zero count entirely; the reply/quote action pins to the row's end via
`ml-auto shrink-0`, the same trick `Control` already used in the header row.

**Why:** A requested visual upgrade toward a more structured, Tailwind-UI-like card shape, kept
daisyUI-token-driven throughout (no hardcoded colors) so Pin's own border-recoloring listener
still works unchanged.

**Status:** Decided & implemented.

---

## 2026-07-20 — Reactions become polymorphic: `reactable_type`/`reactable_id`, a Reply can now carry reactions too

**Decision:** `reactions.moment_id` replaced with a polymorphic `reactable_type`/`reactable_id`
pair; `Reaction::reactable()` is a plain `morphTo()`. Two new, general-purpose mechanisms came out
of this, not reaction-specific: **`Extend\Model::morphAlias()`**, applied via
`Relation::morphMap()` (deliberately not `enforceMorphMap()`, which would throw for any unmapped
model app-wide, including ones unrelated to this feature — caught by a fixture breaking on first
attempt) — lets `reactions` resolve a `{type}` route segment back into a real class without ever
importing `Reply` directly. **Generic polymorphic routes** (`/_reactions/{type}/{id}`, one pair
for any reactable) rather than duplicated Moment-shaped/Reply-shaped routes — `resolveReactable()`
only ever accepts a *registered* morph-map alias, never a raw class name from the URL, since
accepting one would be an IDOR-by-class-name hole.

**Why the generic route over the cheaper duplicate-routes option:** reconsidered once weighing
that reactions was already going polymorphic at the DB layer — a route layer still hardcoding
which concrete types exist would have been the one remaining non-general seam.

**Known pre-existing inconsistency, not touched:** `Card\Top::defaults()`'s own anchor ids are
bare/unprefixed rather than fully-qualified, which structurally never match `SlotResolver`'s
lookup and silently no-op per its own "dangling reference, never an error" rule — happens to
render correctly today only because that also matches registration order. Flagged, not fixed
here.

**Status:** Decided & implemented.

---

## 2026-07-21 — Moment "feature-only" content (image/poll/product with no title+body) is not yet supported; additive attachment (teaser's pattern) already is

**Finding:** Attaching a new kind of content *alongside* a Moment's title+body — image gallery,
poll, product card — is already fully supported with zero core changes, via the same recipe
Discussions' teaser uses: own model/table (`moment_id` FK or polymorphic), `ChangesUx`
registration into `Card\Body`/`Badges`/`Footer`, own composer field via
`kopling-composer::compose.fields`. A Moment that *is only* the feature — no title, no body — is
not supported, and can't be built by an extension alone.

**Why not:** three separate core-owned constraints all assume title+body unconditionally:
`moments.title`/`body` are `NOT NULL`; `StoreMomentRequest::rules()` hardcodes both `required`
(`ValidatesModels` only lets an extension add rules, never relax an existing required one);
`Card\Content`/`card.body.blade.php` render `body_html` and every slot entry's wrapping padded
div unconditionally, so an empty `Content` entry still shows as a blank section rather than
disappearing.

**Not a conflict with the 2026-07-10 "no wholesale component replacement" decision** — this
doesn't ask to swap Card's renderer, only to make its stock `content` entry optional
per-instance, the same "Card stays fixed, only its contents flex" shape.

**Status:** Not implemented, deliberately flagged. Three known touch points if/when a real
feature-only extension needs it: nullable `title`/`body`, a way for `StoreMomentRequest` to know
an attached feature satisfies the "has content" contract, and `Content`/`card.body.blade.php`
collapsing when a slot entry renders nothing.

---

## 2026-07-21 — Extension enable/disable toggle exists now, superseding the 2026-07-10 "flagged for later" entry

**Decision found already implemented, undocumented:** `EnabledExtensions` (a `Settings`-backed
list) plus `kopling:extensions:enable`/`disable` CLI commands and an admin settings toggle now
gate which discovered extensions actually load — the 2026-07-10 entry's "no disable toggle
exists" is no longer true. `all() === null` (bootstrap state, nothing ever toggled) means
everyone's enabled; once the list exists, anything not on it is treated as disabled.

**Real consequence, hit while building `poll`:** a brand-new extension added to an install where
the list already exists is *not* auto-enabled — it silently doesn't load until
`kopling:extensions:enable` is run. `kopling/example` is disabled on this install right now for
the same reason. Worth checking `kopling:extensions:list`/the admin toggle before assuming a
newly-installed extension is inert due to a real bug.

**Status:** Decided & implemented (found this session, not built this session) — CLAUDE.md/
decisions.md just never caught up to it.

---
