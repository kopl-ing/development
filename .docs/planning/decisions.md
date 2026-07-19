# Technical decision history

Engineering-level decision record for this monorepo. This is the technical companion to the
charter's Decision Log (`kopling-landing/public/charter.html`, Section 12): the charter tracks
major, public-facing project decisions in plain language; this file tracks every decision worth
remembering in this codebase — major or minor — in full technical detail, so the *why* isn't
lost behind just the *how* a year from now. See `CLAUDE.md` ("Recording decisions") for the
rule that keeps this file updated.

Entries are dated and append-only; if a decision is later superseded, add a new entry and link
back to the one it replaces rather than editing history away.

---

## 2026-07-09 — Root Laravel installation holds no application code

**Decision:** The root installation (`bootstrap/`, root `composer.json`, `.env`) never contains
application code — no `app/`, no `routes/`, no application-level `resources/views`. All Kopling
code lives in `k-core` (core) and `k-extensions/*` (extensions), auto-registered with Laravel via
Composer package discovery.

**Why:** Keeping the root empty means a developer running Kopling can still use Laravel the
normal way if they want to — add their own `app/`, override a Kopling route or view where they
need to — and it just works, the same as any other Laravel app. Kopling's own code never fights
that by also living at the root.

**Alternatives considered:** A conventional `app/` directory housing Kopling's own code directly
— rejected, forecloses the override/escape-hatch property above. Deferring this decision until a
separate "distribution skeleton" package existed — rejected, no reason to defer something this
cheap to decide correctly now, and it shapes every other structural decision after it.

**Trade-off accepted:** `Illuminate\Foundation\Application::getNamespace()` (used by
`route:list`'s Action-column formatting and `make:*` commands) throws `"Unable to detect
application namespace"` because it looks for a psr-4 `app/` mapping in root `composer.json` that
will never exist here. Cosmetic CLI-only impact — doesn't affect actual routing or rendering.
Not something to fix by adding `app/` back.

**Charter:** candidate for the public Decision Log (major architecture principle) — not yet
proposed there.

---

## 2026-07-09 — Source assets live inside the owning package's own domain folder, not a shared root `resources/`

**Decision:** `k-core`'s CSS/JS source lives at `k-core/src/Ux/css/app.css` and
`k-core/src/Ux/js/app.js`, alongside `k-core/src/Ux/views/` — not in a monorepo-root or
package-root `resources/`. The root only holds the shared Node toolchain (`package.json`,
`node_modules`, the `vite*.config.js` files); every config points *into* the owning package's own
source tree and *into* that package's own `dist/` for output.

**Why:** `k-core` is subsplit into its own standalone repo (`kopl.ing/core`); its assets have to
travel with its own tree the same way its PHP/Blade already does. `Ux/` is core's UX/theming
domain (the `<x-k::*>` component library + the theming logic behind it, per the charter) —
organizing by domain-then-kind (`Ux/{views,css,js}`) keeps this coherent as `k-core` grows more
domains over time, rather than flattening everything generically under one `resources/` bucket
that doesn't reflect the codebase's actual shape.

**Alternatives considered:** A shared root `resources/` (original scaffold; corrected same day) —
rejected, orphaned from `k-core`'s own tree so it would never travel with the subsplit package.
A per-package `resources/` sibling to `src/` (mirroring stock Laravel/Composer package layout) —
viable and still the recommended default for *single-purpose* extensions (see the next entry),
but rejected specifically for `k-core` because `k-core` is expected to grow multiple internal
domains, which domain-first (`src/<Domain>/{views,css,js}`) organizes better than kind-first.

**Status:** Decided & implemented.

---

## 2026-07-09 — k-core ships precompiled CSS/JS as committed release artifacts, compiled only at tag time

**Decision:** `k-core/dist/app.css` and `k-core/dist/app.js` are real, git-committed files,
produced by a dedicated Vite build target (`vite.core-dist.config.js`, fixed unhashed filenames)
and committed to `main` only when a release is prepared (`.github/workflows/release.yml`,
manual `workflow_dispatch`) — never on every ordinary push.

**Why:** `k-core` and each extension are subsplit (splitsh, via `acrobat/subtree-splitter`) into
standalone, readonly Composer packages. splitsh has no build step of its own — it only mirrors
whatever files already exist inside a package's own directory at a given commit. So a Composer
install of `kopling/core` needs the compiled assets to already be real files inside `k-core/`
*before* any split happens. Node/Vite must never be something a Kopling site runs on its own
(possibly shared) host — this is what makes that true: Node only ever runs in CI, at release
time, matching how Filament/Livewire/Statamic ship precompiled assets inside their own Composer
packages. Compiling only at release/tag time (not every push) avoids bot-commit noise on ordinary
development pushes and matches the charter's steady release-cadence principle; it also reuses the
*existing* `create: tags:` trigger in `subsplit.yml` rather than requiring any change there — the
compiled-assets commit rides the existing push-trigger into `kopl.ing/core`'s `main`, and the tag
push rides the existing tag-trigger into the tagged release, both already wired up.

**Alternatives considered:** Compiling and committing on every push to `main` — rejected, noisy,
and most real consumers pin to tagged releases anyway so there's no benefit to the extra churn.
A dual-mode Blade fallback that auto-detects and serves `k-core/dist` when no Vite manifest
exists — not rejected, deliberately deferred (tracked as a TODO in `CLAUDE.md`); until it lands,
`kopling/core` only really works installed inside this monorepo.

**Gotcha discovered during implementation:** Vite's default `publicDir` behavior copies the
monorepo's `public/` (`index.php`, `.htaccess`, the *other* build's `public/build/`, etc.)
straight into any build's `outDir` unless `publicDir: false` is set explicitly. Caught during
verification (a first build silently included Laravel's own public entrypoint files inside what
was about to become the `kopling/core` Composer package) — now set explicitly in
`vite.core-dist.config.js`.

**Status:** Decided & implemented.

---

## 2026-07-09 — Single-purpose extensions get a flat, unprefixed top-level layout

**Decision:** An extension package (e.g. `k-extensions/example`) organizes flat by kind at its
own root: `src/` (PHP, PSR-4), `views/`, `css/`, `js/`, `migrations/`, `routes/` — as direct
siblings, no `k-` prefix on any of them, no `resources/`/`database/` wrapper directories.

**Why:** The `k-` prefix at the monorepo root (`k-core`, `k-extensions`) earns its keep
specifically because it disambiguates Kopling's own directories from a plain Laravel app's
`app/`, `routes/`, `resources/` that might coexist in the same shared root (see the root-holds-no-code
decision above). That collision risk doesn't exist once you're already inside a dedicated package
folder — `k-extensions/example/` is unambiguously Kopling-owned already — so prefixing every
subfolder again is redundant stutter with no precedent in the Laravel package ecosystem, working
against "an extension is PHP and templates, full stop": minimal learning curve for extension
authors already fluent in ordinary Laravel package conventions matters more than alphabetically
grouping asset folders away from `src/` in a directory listing.

**Alternatives considered:** Prefixing every extension subfolder with `k-` (e.g. `k-views/`,
`k-css/`) so `src/` sorts distinctly apart from them — rejected, purely cosmetic, no functional
benefit, and unfamiliar to anyone who's built an ordinary Laravel package before. Nesting under
`resources/`+`database/migrations/` wrappers, matching a stock new Laravel app — rejected per an
explicit request for the flattest structure possible; a single-purpose extension doesn't have
enough internal complexity to need that extra nesting the way `k-core` might.

**Status:** Decided & implemented — `k-extensions/example` (2026-07-09) is the working reference.

---

## 2026-07-09 — Two-tier decision recording: charter for major/public decisions, this file for full technical detail

**Decision:** Major, project-wide decisions get proposed as a diff to the charter's Decision Log
(`public/charter.html`, Section 12) in plain language with a one-line rationale (existing working
agreement). Every decision worth remembering in this codebase — major or minor, in full technical
detail, alternatives considered and why they were rejected — additionally gets its own entry in
this file.

**Why:** The charter is plain-language and public-facing by design (Section 10: "plain language
for a worldwide audience"), so it deliberately can't and shouldn't carry full technical rationale
for every implementation-level choice — most of the entries in this file (Vite build target
layout, `publicDir` gotchas, directory-naming trade-offs) would be noise to that audience. This
file exists so that engineering "why" survives for contributors and coding agents working in this
codebase specifically, without diluting the charter's own purpose.

**Status:** Decided & implemented (this file, and the `CLAUDE.md` section describing the rule).

---

## 2026-07-09 — Extension entry point: a plain `AbstractExtension`, discovered by `"type": "kopling-extension"`, directory-convention auto-registration, contracts only for genuinely behavioral capabilities

**Decision:** Every extension ships one required file, `src/Extension.php`, `class Extension
extends Kopling\Core\Extension\AbstractExtension`, implementing static `name()`/`description()`.
`AbstractExtension` has no relationship to `Illuminate\Support\ServiceProvider` at all — it's a
plain, Kopling-owned class. Discovery: an extension's `composer.json` declares `"type":
"kopling-extension"`; `Kopling\Core\Extension\Manifest` (a rewritten `PackageManifest` subclass)
filters `installed.json` by that type and derives each extension's namespace + on-disk path,
caching to `bootstrap/cache/kopling-extensions.php`. `Kopling\Core\Extension\Manager` instantiates
each discovered `<namespace>Extension` and exposes `conventions($package)`: whichever of
`migrations/`, `views/`, `css/`, `js/`, `routes/`, `lang/` exist under that package's own root, by
directory presence alone — no interface to implement for any of these. `Kopling\Core\Provider\ServiceProvider::boot()`
loops over every discovered extension and registers each found convention via Laravel's own
`loadMigrationsFrom`/`loadViewsFrom`/`loadRoutesFrom`/`loadTranslationsFrom`, namespaced by the
package's short name (`kopling/reactions` → `reactions`). Contracts
(`Kopling\Core\Extension\Contract\*`, e.g. `RequestsStorageDriver`) exist only for capabilities a
directory can't express — `Manager` discovers which an extension implements via `instanceof`,
no separate declaration list needed.

**Why:** "Mainstream tool inside, sovereign contract outside" argues for wrapping
`ServiceProvider`, but a *ServiceProvider subclass extensions extend directly* would still leak
Laravel's own API surface into extension code the moment an author reached for `$this->app`
inside it — the opposite of the isolation the rule is for. Keeping `AbstractExtension` fully
plain means the *only* things an extension author ever touches are `Kopling\Extend\*`
(reserved for wrapped Laravel primitives, not yet populated) and `Kopling\Core\Extension\*`; all
actual Laravel wiring happens inside `Manager`/`ServiceProvider`, code no extension ever writes.
Directory-convention-over-configuration removes essentially all authoring ceremony for the common
cases (migrations/views/routes/lang), matching "an extension is PHP and templates, full stop";
contracts are reserved for the genuinely small set of things that can't be inferred from a
filesystem check (declaring a storage driver capability being the first and, so far, only one).

**Naming note:** the abstract base is `AbstractExtension`, not `Extension` — every extension's own
class is named `Extension`, so if the base class shared that name, any extension file would need
`use Kopling\Core\Extension\Extension as Something;` to avoid two symbols named `Extension` in one
file. The `Abstract*` prefix is the standard escape from that collision.

**Coding convention introduced by this decision, applies project-wide:** never mark a method or
class `final`. It directly undercuts the charter's own escape-hatch philosophy ("Outlets compose;
overrides don't" — the override path only stays real if nothing is ever sealed shut).

**Alternatives considered:** Flarum's `extend.php` returning a plain array of "Extender" value
objects — rejected, an uncached, un-autoloadable, un-typed shape is a worse fit here than a real
namespaced class, and the introspectability it buys (core can see what an extension does without
executing it) is fully available another way. A generic declarative array of typed extender
objects returned from one method on `Extension` — considered as a middle ground, then dropped in
favor of small capability interfaces (`ProvidesRoutes`-style, one per concern) once directory
conventions turned out to cover everything except genuinely behavioral capabilities — interfaces
plus `instanceof` give the same introspectability with less indirection and better IDE/PHPStan
support. `Extension extends ServiceProvider` — rejected, see Why above. Reusing Laravel's own
`PackageManifest`/`bootstrap/cache/packages.php` directly instead of a parallel `Manifest` —
rejected: traced Laravel's actual `PackageManifest::build()` and confirmed its trailing
`->filter()` silently drops any package with an empty `extra.laravel` value, so it structurally
cannot answer "find me every package of a given Composer `type`."

**Status:** Decided & implemented. First working reference: `k-extensions/reactions` (updated) and
`k-extensions/example` (built as the canonical dummy, exercising every convention + the one
existing contract). `css`/`js` conventions are detected but not yet linked onto the page — still
waiting on the `head.assets` outlet (separate, not-yet-made decision).

---

## 2026-07-09 — Extension view/translation namespaces include the vendor, not just the package name

**Decision:** Corrects the entry above. `Kopling\Core\Extension\Manager::id($package)` derives an
extension's view/translation namespace as the *full* Composer package name with `/` replaced by
`-` (`kopling/example` → `kopling-example`), not the package name alone with the vendor stripped
(what `Kopling\Core\Provider\ServiceProvider::boot()` originally did, via `Str::after($package,
'/')`). `view('kopling-example::hello')` / `__('kopling-example::messages.hello')`, not
`example::`.

**Why:** Two different vendors can each publish an extension called `example` (or any other
generic name) — `kopling/example` and `acme/example` are both entirely plausible. Deriving the
namespace from the package name alone means both would register the identical `example::`
namespace, and the second one installed would silently collide with (or overwrite) the first.
Including the vendor makes the namespace as unique as the Composer package name already
guarantees itself to be, at zero extra cost to the extension author — they don't declare this
namespace themselves, `Manager` derives it the same way either way.

**Alternatives considered:** Keeping the short name and requiring extension authors to pick a
manually-declared, globally-unique namespace themselves — rejected, reintroduces exactly the kind
of manual registration step the directory-convention system exists to avoid. A different
separator (e.g. `.` or keeping the `/`) instead of `-` — `-` was chosen as the simplest one that
reads cleanly in both `view()`/`__()` calls and doesn't need escaping in Blade.

**Status:** Decided & implemented. `Manager::id()` is now the single place this derivation
happens; `k-extensions/example`'s own views/routes/lang updated to the corrected namespace;
`kopling-landing/public/extend.html` updated to document the correct scheme.

---

## 2026-07-09 — Person/Group are the real Authenticatable model and its group relation, UUID-keyed

**Decision:** `Kopling\Core\People\Person` extends `Illuminate\Foundation\Auth\User` (the
`Authenticatable` base), backed by a `people` table; `Kopling\Core\People\Group` is a plain
Eloquent model backed by `groups`, related to `Person` many-to-many via a `group_person` pivot.
Both use `HasUuids` for UUID primary keys. The `web` guard's `users` provider is pointed at
`Person::class` by k-core's `ServiceProvider::register()` calling
`$this->app['config']->set('auth.providers.users.model', Person::class)` — `config/auth.php`
itself is left untouched (still stock Laravel, still nominally defaulting to the nonexistent
`App\Models\User`, which is harmless since `::class` never triggers autoloading).

**Why:** These classes already existed as placeholders with `config/auth.php` already commented
"Person Providers" — this wires that existing naming decision up to something that actually
works. UUID primary keys match the convention already established by
`k-extensions/example`'s migration (`$table->uuid('id')->primary()`). A `Group` with no way to
relate to a `Person` is inert, hence the pivot. Setting the model via `config()->set()` in
`register()` (config files load before any provider's `register()` runs) rather than editing
`config/auth.php` directly keeps that root config file untouched — consistent with the "root
holds no application code" rule, the same reasoning applied to keeping middleware registration
out of `bootstrap/app.php` (see the entry below).

**Status:** Decided & implemented.

---

## 2026-07-09 — htmx auth-wall responses use `HX-Redirect`, via an `ExceptionHandler::renderable()` callback registered from k-core's ServiceProvider

**Decision:** `Kopling\Core\Http\Exceptions\RedirectHtmxUnauthenticated` is an invokable
(`__invoke(AuthenticationException $e, Request $request): ?Response`) that, only for requests
carrying the `HX-Request` header, returns a 401 with an `HX-Redirect` header
(`Route::has('login') ? route('login') : '/login'`) instead of letting the framework's default
unauthenticated handling run; it returns `null` for non-htmx requests, falling through to
Laravel's normal behavior. Registered via
`$this->app->make(ExceptionHandler::class)->renderable(new RedirectHtmxUnauthenticated())` inside
`ServiceProvider::boot()` — not declared in root `bootstrap/app.php`'s `withExceptions()` closure.

**Why:** htmx swaps response HTML into whatever element issued the request; a normal
redirect-to-login response gets its HTML fragment swapped into that target instead of navigating
the whole page, stranding a login form mid-page. `HX-Redirect` is htmx's documented mechanism for
this — the client processes it unconditionally on arrival and does `window.location = ...`
regardless of status code, so pairing it with 401 matches htmx's own docs example. Registering
from k-core's `ServiceProvider::boot()` rather than `bootstrap/app.php` follows the standing
architecture rule (see "Root Laravel installation holds no application code" above) — `renderable()`
is a fully supported Laravel API for registering exception-render callbacks from anywhere the
container is available, not just from `bootstrap/app.php`'s closure.

**Rejected first attempt, and why it failed:** The original design was a `Middleware` class
(`Kopling\Core\Http\Middleware\RedirectHtmxUnauthenticated`) wrapping `$next($request)` in a
try/catch for `AuthenticationException`, pushed onto the `web` group via
`Route::pushMiddlewareToGroup()`. Verified by hand with a scratch route that throws
`AuthenticationException` directly: the catch block never ran (confirmed via temporary logging)
— curl against the route returned a framework 500 (`RouteNotFoundException` from the default
`redirectTo()` handling) for *both* htmx and non-htmx requests. Root cause, found by reading the
actual stack trace: `Illuminate\Routing\Pipeline` (the pipeline Laravel uses specifically for
route-level middleware) overrides exception handling so that any `Throwable` raised while running
the route + its route middleware is converted into a `Response` via the exception handler's
`render()` *before* it ever propagates back up through enclosing route middleware as a real PHP
exception — so `$next($request)` inside a route-group middleware never throws; it just returns
the already-rendered error response. A try/catch around `$next()` in route-level middleware can
therefore never observe an exception thrown deeper in that same route's pipeline. The
`renderable()` hook is the actual interception point Laravel itself uses for this conversion, which
is why registering there works.

**Alternatives considered:** An `AuthenticationException` `render()` closure directly in
`bootstrap/app.php`'s `withExceptions()` — functionally equivalent to what we ended up doing, but
rejected as the registration *site* since that file is root-owned bootstrap/build tooling only,
and CLAUDE.md already establishes that k-core's `ServiceProvider` is where "further config,
middleware, or bindings" get registered as the codebase grows.

**Trade-off accepted / not yet resolved:** No login route/controller exists yet, even though
`Person` is now a real, table-backed model (see the entry above). The callback degrades via
`Route::has('login') ? route('login') : '/login'` rather than a hard `route('login')` call.
Revisit the literal `/login` fallback once a real login route lands.

**Status:** Decided & implemented.

---

## 2026-07-10 — `StorageRequest` capabilities: access / retention / permission, backend never named

**Decision:** `Kopling\Core\Storage\StorageRequest` declares a named storage purpose
(`key`, `label`, `description`) plus three independent, backed enums: `StorageAccess`
(`Private` — no URL, app-mediated reads only; `Public` — stable permanent URL; `Signed` —
private content exposed via short-lived signed URLs, i.e. Laravel's `temporaryUrl()`),
`StorageRetention` (`Cache` — safe to purge, app regenerates it; `Persistent` — durable,
never regenerated), and `StoragePermission` (`ReadOnly`; `ReadWrite`). Nothing on the class
names a backend (local disk, S3, cloud, whatever) — that mapping is an admin-configured
choice, entirely outside the extension's concern. `Kopling\Core\Extension\Manager::storageDrivers()`
was also fixed to return `array<string, array<StorageRequest>>` keyed by `id($package)`
(the same scheme already used for view/translation namespacing) instead of flattening every
extension's requests into one anonymous array via `array_push(...)` — the flattened form lost
which extension owned which request, information the future admin storage-mapping screen needs.

**Why:** The charter's original draft list of capabilities (public URL, signed URL, streaming,
cloud) was provisional, not decided, and "cloud" specifically conflates a request-side concern
with a backend/admin-side one — whether a mapped drive happens to be local disk, S3, or
something else is never something the requesting extension should know or declare. Access and
retention are genuinely orthogonal (a `Public`+`Persistent` avatar and a `Private`+`Cache`
thumbnail-render are both coherent, so neither collapses into a single "purpose" enum).
`ReadOnly`/`ReadWrite` was added on top because Laravel/Flysystem has a real read-only disk
wrapper for exactly this distinction, and it's security-relevant: an extension serving only
vendored/pre-seeded assets has no business being granted write access to whatever drive it gets
mapped to.

**Rejected from consideration:** A "streaming" capability (in the original charter draft) —
dropped, not a meaningful per-request differentiator across Flysystem adapters. A "node-locality"
capability (does this purpose require storage shared across app instances, vs. tolerating a
single node) — real concern on horizontally-scaled infra (a `local` disk is only visible to the
node that wrote it), but rejected as a `StorageRequest` field since local-disk drivers can
already be wrapped behind something replicated/shared at the admin-config level; treated as an
admin/driver-mapping concern, not something the extension declares. A TTL/expiry field for
`Signed` access — rejected, Laravel's `temporaryUrl($path, $expiration)` takes expiration at
call time, not disk-configuration time, so freezing a default into the request is premature.
File-size/MIME-type constraints — rejected outright, that's upload-request validation, unrelated
to which drive a purpose resolves to.

**Explicitly deferred, not yet decided:** Whatever resolves a `StorageRequest` to an actual
configured drive (an admin-mapping screen + resolver, neither built yet) must never silently
fall back to a different drive when a request is unmapped or its mapped drive is unavailable —
on scalable/multi-node infra, a silent fallback would quietly break the app for a fraction of
users rather than failing loudly for everyone. This constrains the not-yet-built resolver; it
doesn't yet have an owning entry of its own since no resolver code exists.

**Status:** `StorageAccess`, `StorageRetention`, `StoragePermission`, `StorageRequest`, and the
`Manager::storageDrivers()` fix implemented. `k-extensions/example`'s `Extension::storage()`
updated to construct a real request (an `avatars` purpose: `Public`, `Persistent`, `ReadWrite`).
Admin storage-mapping UI and the request→drive resolver are not yet built. Charter's Storage line
(`public/charter.html`) still reflects the old, provisional capability list and needs updating to
match — not yet done, lives in the `kopling-landing` repo.

---

## 2026-07-10 — Permissions: granular named strings under `Kopling\Core\Authorization`, prefixed by extension id, no hardcoded admin flag

**Decision:** `Kopling\Core\Authorization\Permission` is a plain value object (`id`, `label`,
`description`, optional `?\Closure $callback`). Extensions declare theirs via
`Kopling\Core\Extension\Contract\HasPermissions::permissions(): array<Permission>`, writing only
the local part of the id (e.g. `manage-things`) — `Manager::permissions()` prefixes it with the
owning extension's `id()`, joined with `::` (`kopling-example::manage-things`) before it's ever
registered — the same separator `id()` already produces for view/translation namespaces. Core's
own permissions
(`Kopling\Core\Authorization\CorePermissions::all()`) are written fully prefixed with `core::`
directly by core itself, since core isn't discovered through `Manager` and has no collision risk
to guard against. `ServiceProvider::boot()` collects `[...CorePermissions::all(),
...$manager->permissions()]` and `Gate::define()`s each one. Storage: no `permissions` table —
`Person`/`Group` (see the entry above on those becoming the real auth model) already exist;
`Group` gets `hasPermission()`/`givePermissionTo()`/`revokePermissionTo()` against a new
`group_permission` pivot (`group_id` + a raw `permission` string, composite primary key, no FK to
a permissions table since none exists); `Person::hasPermission()` joins through `group_person` to
check across all of a person's groups. Every `Gate::define()`'d ability runs the same base check
first — `$person->hasPermission($permission->id)` — and only calls `$permission->callback` (if
present) as an *additional* condition layered on top; the callback can narrow access, never grant
it on its own.

**Why:** Named, granular permissions with roles/groups as nothing more than assignable bundles is
the direct fix for a real, lived Flarum flaw — a single binary "administrator" flag with no way to
grant partial admin access (see the charter, D29: "Portals & permissions"). Prefixing by extension
id the same way views/translations already are means an author never has to think about another
extension choosing the same permission name — `Manager` guarantees uniqueness structurally, not by
convention someone has to remember. No `permissions` table exists because a permission's
definition (label, description, callback) lives in code and is recomputed fresh every request via
`Manager::permissions()` — storing it as its own DB row would mean reconciling that row every time
an extension is installed, updated, or removed, for no benefit: the only fact that actually needs
to persist is the grant (which group has which permission string), so that's the only thing the
schema stores. The callback is deliberately a *narrowing* condition, never a replacement for the
base grant check, for the same reason `final` is never used anywhere in this codebase (see the
entry on the extension entry point) — an escape hatch that could bypass the grant check entirely
would be exactly the kind of foot-gun the base check exists to prevent.

**Alternatives considered:** A `permissions` database table, rows synced from code (the
`spatie/laravel-permission` approach) — rejected for the reconciliation-on-every-change cost above,
with nothing gained since Kopling doesn't need to query permission metadata relationally, only
check grants. Namespaced/scoped permissions (a Kubernetes-RBAC-style per-community scope, discussed
alongside Portals) — explicitly out of scope for now: Kopling isn't building for a multi-community
platform at this stage, so the schema stays flat (one set of groups, not one set per community).
The callback fully replacing the base check instead of narrowing it — rejected as a foot-gun, see
Why above.

**Status:** Decided & implemented. Proven via `k-extensions/example` (`RequestsStorageDriver` and
`HasPermissions` both implemented on the same `Extension` class) and one core permission
(`core::manage-people`). Verified end-to-end: grant/revoke through `Group`, `$person->can()`
resolving correctly for both a `core::`-prefixed and an extension-prefixed permission
independently, and an unregistered permission safely denying. `label`/`description` now go
through the extension's own `lang/` via Laravel's `__()` helper (`kopling-example::permissions.
manage-things.label`) rather than being hardcoded strings — verified this resolves correctly,
since `ServiceProvider::boot()` already registers every extension's translations before it
collects permissions. Not yet built: any admin UI for assigning permissions to groups (today,
only the `Group::givePermissionTo()`/`revokePermissionTo()` PHP API exists).

---

## 2026-07-10 — `bootstrap/cache/kopling-extensions.php` and `database/database.sqlite` are fixed by composer hooks, not documented as manual steps

**Decision:** `Kopling\Core\Console\Commands\DiscoverExtensions` (`php artisan
kopling:extensions:discover`) rebuilds `Manifest`'s cache the same way Laravel's own built-in
`Illuminate\Foundation\Console\PackageDiscoverCommand` (`package:discover`) rebuilds
`packages.php` — resolve the manifest from the container, call `->build()` unconditionally, no
file-deletion dance required. Root `composer.json`'s `post-autoload-dump` now runs it right after
`package:discover`, and also runs a `@php -r` one-liner that creates `database/` and
`database/database.sqlite` if either is missing (the standard modern-Laravel-skeleton pattern for
exactly this), before either artisan call.

**Why:** Both gaps were found the same way — hit repeatedly during the extension/permission work,
worked around by hand each time (`rm -f bootstrap/cache/kopling-extensions.php` before nearly
every test; `mkdir database && touch database/database.sqlite` once per fresh checkout) — and the
first instinct was to write "remember to do this" into `CLAUDE.md` rather than ask whether the gap
itself should just be closed. Both are cheap, standard fixes (an Artisan command mirroring a
pattern Laravel already ships; a composer script Laravel's own skeleton already uses for exactly
this purpose) — worth doing instead of asking every future session to remember a manual step.

**Alternatives considered:** A raw `rm -f bootstrap/cache/kopling-extensions.php` shell command
directly in the composer script — rejected in favor of a proper Artisan command, matching how
`package:discover` itself works (resolve + rebuild, not delete + lazy-rebuild-on-next-access) and
staying usable standalone (`php artisan kopling:extensions:discover`), not just from Composer.

**Status:** Decided & implemented. Verified from a genuinely clean state: deleted both
`bootstrap/cache/kopling-extensions.php` and the entire `database/` directory, ran `composer
dump-autoload` cold, confirmed both were recreated correctly, then ran `php artisan migrate`
successfully against the fresh SQLite file.

---

## 2026-07-10 — Portals: named UI surfaces declared through `Core`, an `AbstractExtension` `Manager` always loads first; never a second gating mechanism

**Decision:** `Kopling\Core\Portal\Portal` is a plain readonly VO (`id`, `label`, `path`,
`layout`) — a route-prefix + Blade-layout pairing, mirroring `Permission`'s shape exactly.
`Kopling\Core\Extension\Contract\HasPortals` (`portals(): array<Portal>`) mirrors
`HasPermissions`. `Kopling\Core\Extension\Manager::portals()` mirrors `Manager::permissions()`
precisely: loops every `instanceof HasPortals` entry in `extensions()`, prefixing `Portal::$id`
with the owning package's `id()` the same way permission ids are prefixed.

Core's own permissions and portals are declared through a new `Kopling\Core\Core` class —
`extends AbstractExtension implements HasPermissions, HasPortals`, writing **local** ids
(`manage-people`, `manage-theme`, `community`, `admin`) exactly as any real extension would.
`Manager::extensions()` now always prepends `'core' => new Core()` before the Composer-discovered
entries, so `Core` runs through the identical `permissions()`/`portals()`/`storageDrivers()` loops
as everything else — no special-cased merge anywhere. `ServiceProvider::boot()`'s `Gate::define()`
loop simplified from `[...CorePermissions::all(), ...$manager->permissions()]` to plain
`$manager->permissions()`.

Two portals exist today: `core::community` (`path: ''`, existing `/` route/layout untouched — no
functional rewiring, this entry exists purely so the registry isn't hardcoded to a single
non-default portal) and `core::admin` (`path: 'admin'`, new `k-core/src/routes/admin.php` +
`admin.blade.php`). The Admin portal's first and only route today, `core::admin.theme`
(`ThemeController`, gated by a new `core::manage-theme` permission), is a thin placeholder proving
the chain end to end — no token storage/validator/editor yet, that's separate follow-up work.

**Why:** `CorePermissions::all()` previously hand-wrote fully-prefixed ids (`core::manage-people`)
as a special case, because core "isn't discovered through `Manager`" — a real asymmetry between
how core and extensions authored the same kind of thing. Adding a parallel `CorePortals::all()` for
the new Portal concept would have been a *third* copy of the same special-casing. Making `Core`
itself an `AbstractExtension` implementor removes the asymmetry structurally: there is exactly one
way to declare a permission or a portal (implement the contract, write a local id), and exactly one
place that does the prefixing (`Manager`), regardless of whether the declarer is core or a real
extension. This is also the direct, structural fix for charter D29's "a Portal is a registrable
pattern, not fixed to exactly two" — the Moderation portal (D29's own named future proof case) is a
`HasPortals`-implementing extension away, no `Manager`/`ServiceProvider` changes required.

**Portal is explicitly not a gating mechanism.** No `canAccessPortal()`-style check exists anywhere
— that would be exactly the disguised hardcoded-admin-flag D29 rejects (the real, lived Flarum
flaw: a binary admin flag with no partial access). `core::admin.theme` gates itself with ordinary
`can:core::manage-theme` route middleware, identical to how any route anywhere would. The Admin
layout's nav is a single `@can`-gated link, not a portal-level visibility flag — "is this portal
worth showing" is answered by aggregating over its own routes' real gates, not a separate check.

**Why no `auth` middleware on the admin route group:** no `login` route exists yet (existing,
separately-tracked gap — see the htmx auth-wall entry above). Laravel's stock
`Authenticate::redirectTo()` calls `route('login')` unconditionally for non-htmx requests, which
would throw `RouteNotFoundException` — the identical failure class already diagnosed once for the
htmx auth-wall. `can:core::manage-theme` alone is sufficient: Laravel's `Gate` auto-denies (never
invokes the callback) when an ability's callback requires a non-nullable typed user and the
resolved user is null, so a guest cleanly gets `403`, never a crash. Verified over real HTTP
(`php artisan serve`): guest `GET /admin/theme` → `403`; `Gate::forUser()` with a person granted
`core::manage-theme` → `ALLOW`, with a person that isn't → `DENY`, matching the same
`Gate::define()` closure the `can:` middleware itself calls.

**Why the route name is `core::admin.theme`, not `admin.theme`:** `Portal::$id` gets the same
`::`-prefixing treatment as `Permission::$id` for the same collision-safety reason (two extensions
could otherwise both declare a portal literally named `admin`). `::` inside a route name is
unusual by stock Laravel convention, but consistent with every other namespace this codebase
already uses (views, translations, permissions) — chosen deliberately over inventing a second
separator just for routes.

**Why `app.blade.php`'s `<head>` was extracted into `layouts/partials/head.blade.php`:** the new
`admin.blade.php` needs the identical `<head>` (charset, viewport, csrf meta, `@vite(...)`), and a
separate, still-pending piece of work (runtime theme-token `<style>` injection, discussed but not
yet built) will need to land in that shared head exactly once rather than in two
independently-drifting layout files. Verified behaviorally identical after extraction (`GET /`
renders byte-for-byte the same content, confirmed against a real `npm run build` + HTTP request).

**Alternatives considered:** A `CorePortals::all()` static registry mirroring the (now-removed)
`CorePermissions` — rejected once the asymmetry it would have repeated was noticed; folding into
`Core` removes it instead of adding a third instance of it. A nav-item registry inside the Admin
portal — rejected as premature for a single route (`n=1`); a hardcoded `@can`-gated link is the
correct-sized answer until a second admin route needs ordering/choosing between items. `auth`
middleware on the admin group — rejected, see the login-route reasoning above.

**Status:** Decided & implemented. `k-core/src/Core.php`, `k-core/src/Portal/Portal.php`,
`k-core/src/Extension/Contract/HasPortals.php`, `k-core/src/routes/admin.php`,
`k-core/src/Http/Controllers/Admin/ThemeController.php`,
`k-core/src/Ux/views/layouts/admin.blade.php`,
`k-core/src/Ux/views/layouts/partials/head.blade.php`, `k-core/src/Ux/views/admin/theme.blade.php`.
`Authorization/CorePermissions.php` deleted. Verified end-to-end over real HTTP and via `Gate`
directly (guest 403, granted/ungranted `Person` allow/deny, `Gate::has()` for both
`core::manage-people` and `core::manage-theme` post-refactor, `GET /` unaffected). Not yet built:
the theme editor's real internals (token storage, `ThemeValidator`, live-preview) behind
`core::admin.theme` — tracked as separate, later work.

---

## 2026-07-10 — `ChangesUx`/`Ux`: one contract for every UI extension point, not one per surface; supersedes the "nav registry premature at n=1" call above

**Decision:** `Kopling\Core\Extension\Contract\ChangesUx` (`ux(): Ux`) is the single contract for
an extension (or `Core`, same as `HasPermissions`/`HasPortals`) to place UI into any named slot —
side navigation today, head assets/post actions/admin widgets later — rather than a new capability
interface per surface. `Kopling\Core\Extend\Ux` is a fluent builder mirroring Laravel's own
`Route::get()->name()->middleware()` chaining: `Ux::make()->add($component, $data)->in($slot)
->after($id)->before($id)->as($id)->when($condition)`, each call mutating the `UxEntry` started by
the most recent `add()`. `Kopling\Core\Ux\UxEntry` holds one registered placement — unlike
`Permission`/`Portal`/`StorageRequest`, deliberately **not** readonly, since the builder mutates it
incrementally as the chain continues.

Two things get different treatment, and the difference is deliberate:
- **`$slot`** is a fully-qualified string the author writes out in full (`"core::side-navigation"`)
  and is **never** auto-prefixed by `Manager` — a slot is a public rendezvous point other
  extensions must be able to name exactly; auto-prefixing it the way permission ids are would make
  it impossible for one extension to place something into a slot owned/rendered by another.
- **`$id`** (defaults to the `$component` string if `as()` is never called) and a string
  `$condition` (a local permission id) **are** prefixed by `Manager::ux()`, the same treatment
  `permissions()` gives `Permission::$id` — these are private to the declaring extension, so
  `after('core::theme')`/`when('manage-things')` read the same low-ceremony way permission ids
  already do.

`Kopling\Core\Extension\Manager::ux(): UxEntryCollection` loops `extensions()` (Core included, same
as `permissions()`/`portals()`) filtering `instanceof ChangesUx`. `Kopling\Core\Ux\SlotResolver::
resolve(string $slot, UxEntryCollection $entries): UxEntryCollection` turns that flat, unordered
collection into what one slot actually renders: filters to the slot, best-effort positions entries
via `after`/`before` (a reference to a missing/uninstalled entry is silently ignored, never an
error — "outlets compose; overrides don't"), then filters by `condition` (`Gate::allows()` for a
string, direct call for a closure). Every component a `UxEntry` can render takes exactly one
constructor param, `array $data` — passed whole, never spread into named props — so `SlotResolver`/
`Manager` never need to know an individual component's prop names to render it via
`<x-dynamic-component :component="$entry->component" :data="$entry->data" />`.

`Core::ux()` now registers the Admin portal's Theme link this way (`.as('theme')
.when('manage-theme')`), replacing `layouts/admin.blade.php`'s previous hardcoded `@can(...)` link.
`k-extensions/example` implements `ChangesUx` too, registering a demo item `.after('core::theme')
.when('manage-things')` — proving cross-extension anchoring and independent gating end to end.

**Supersedes:** the "nav-item registry inside the Admin portal — rejected as premature for a single
route (n=1)" call in the Portals entry above. That was correct at the time (one hardcoded link, no
second consumer yet); it stopped being true the moment a second, real consumer (an extension
wanting its own side-navigation entry) needed the same placement without core hand-editing a Blade
`@can` block for every extension that ever wants a link. Building the *general* mechanism now,
rather than a narrow nav-only registry, avoids paying this same design cost again for the next
surface (head assets, post actions, admin widgets) — each of those would otherwise want its own
contract interface, bloating both `AbstractExtension`'s surface and every extension's own
`Extension.php` with one more method per UI surface it touches.

**Why one contract instead of `HasNavigation` narrowly:** a narrow contract solves nav today but
repeats the exact shape (value object + contract interface + `Manager` collector) for every future
surface — `HasPermissions` and `RequestsStorageDriver` already show that pattern doesn't stay cheap
past two instances. `ChangesUx`/`Ux` generalizes the aggregation shape once (still `instanceof`-
discovered, still `Manager`-collected, still id-prefixed the same way) while the fluent builder
carries the per-surface specifics (`slot`, ordering, condition) as data rather than as new methods
on the contract itself.

**Plain `Illuminate\Support\Collection`, not a custom typed subclass:** `Manager::ux()`/`portals()`
and `Ux::entries()` return an ordinary `collect(...)` — a `UxEntryCollection`/`PortalCollection`
pair `extends Collection` calling `$this->ensure(...)` in the constructor was tried first, then
reverted the same session: `ensure()` only re-validates what the *constructor* receives, but
`put()`/`push()`/`merge()` (and most of `Collection`'s other mutators) write straight into the
internal `$items` array without ever calling `new static(...)` — so the guarantee silently stopped
holding the moment anything touched the collection after construction, which defeats the reason to
have a typed collection in the first place. A real fix (overriding every mutating method, or
validating lazily on each read) is more ceremony than a handful of small, short-lived collections
here warrant.

**Alternatives considered:** A `HasNavigation` contract narrowly scoped to nav — rejected per the
"supersedes" reasoning above. A static `Outlet::add('post.actions', 'reactions::button')` facade,
matching the charter's own marketing sketch literally — rejected in favor of routing registration
through the same `instanceof`-discovered contract + `Manager`-collector shape every other
extension-declared capability already uses, rather than a bare static call with no
extension-scoping (a static facade call has no natural place for `Manager` to know which extension
made it, needed for id-prefixing). `Ux` passed into `ux(Ux $ux): void` as a mutable parameter
instead of returned from `ux(): Ux` — rejected, `Ux::make()` returned by the method reads closer to
how `permissions()`/`portals()`/`storage()` already return their own value, and needs no
special-cased "empty builder" the extension didn't create itself.

**Status:** Decided & implemented. `k-core/src/Ux/{Ux,UxEntry,SlotResolver}.php`,
`k-core/src/Extension/Contract/ChangesUx.php`, `Manager::ux()`/`Manager::portals()`, `Core::ux()`,
`k-extensions/example`'s `Extension::ux()`.
Not yet built: any slot beyond `core::side-navigation` (head assets, post actions, admin widgets) —
the mechanism generalizes to them without further contract/`Manager` changes, only a new consuming
component. `kopling-landing/public/extend.html` needs a matching update documenting `ChangesUx`
alongside `RequestsStorageDriver`/`HasPermissions` — separate repo, not done as part of this entry.

---

## 2026-07-10 — Core Ux components: PHP class path mirrors Blade view path, domain-nested; extensions stay flat

**Decision:** A core-owned `<x-k::*>` component's PHP class path mirrors its Blade view path
1:1, domain-nested under `Ux/` — e.g. `k-core/src/Ux/Portal/Navigation/Side.php` (class
`Kopling\Core\Ux\Portal\Navigation\Side`) pairs with
`k-core/src/Ux/views/portal/navigation/side.blade.php`, registered as `<x-k::portal.navigation
.side>`. `Blade::componentNamespace('Kopling\Core\Ux', 'k')` (registered once, in
`ServiceProvider::boot()`) resolves the dot-separated tag directly against the nested PHP
namespace — Laravel's own component-namespace resolution already dot-splits nested folders, no
custom resolution needed. This convention is **core-only**: an extension's own components (if it
ever ships one) stay flat/simple, the same as `views/`/`css/`/`js/` already do — never required to
adopt this nesting.

**Why:** Core is expected to grow many components across several UI domains over time (Portal
chrome, navigation, later probably forms/actions/etc., per the charter's own `<x-k::action>`
example) — a flat `Ux/views/components/*.blade.php` bucket (the stock Laravel package convention)
doesn't scale legibly past a handful of components the way domain-then-kind organization already
proved out for `k-core`'s CSS/JS (see the "source assets live inside the owning package's own
domain folder" entry above — same reasoning, applied one level deeper: domain, then kind, then
component name). Mirroring the PHP path and the Blade path 1:1 means a contributor can always find
one from the other by inspection, without needing a lookup table. Extensions don't get this
requirement because they're single-purpose and flat by design (see the "single-purpose extensions
get a flat, unprefixed top-level layout" entry above) — forcing domain-nesting onto a package that
usually has one or two components of its own would be exactly the kind of premature structure that
entry already rejected for extensions generally.

**Alternatives considered:** A flat `Ux/View/Components/*.php` + `Ux/views/components/*.blade.php`
bucket, matching the stock Laravel package-component convention — rejected for the scaling reason
above; also would have meant registering a narrower `Blade::componentNamespace('Kopling\Core\Ux
\View\Components', 'k')` pointing one level deeper, which is exactly the indirection the mirrored
convention avoids. Requiring extensions to adopt the same nesting for consistency — rejected,
directly contradicts the existing flat-extension-layout decision for no real benefit (an extension
with one component doesn't need a domain hierarchy to organize it).

**Status:** Decided & implemented. First working reference:
`k-core/src/Ux/Portal/{Layout,Navigation/Side,Navigation/Item}.php` and their mirrored
`k-core/src/Ux/views/portal/{layout,navigation/side,navigation/item}.blade.php`.

---

## 2026-07-10 — One id field, mutated in place by `Manager`, across every extension-declared value object

**Decision:** `Permission`, `Portal`, `UxEntry`, and now `StorageRequest` all name their local
identifier `$id` (not `key`, not anything else) and declare it as a plain mutable property —
never `readonly`, and the class itself is never `readonly class` either, even though every other
property on these value objects stays `readonly`. `Manager`'s four collectors
(`permissions()`/`portals()`/`ux()`/`storageDrivers()`) all prefix that same `$id` the same way:
mutate it in place (`$declared->id = $this->id($package).'::'.$declared->id;`) and keep the same
object, rather than constructing a new copy of the value object with every other field
hand-repeated just to attach a prefix.

`StorageRequest::$key` is renamed to `$id` as part of this pass, and `storageDrivers()` now
prefixes it (`kopling-example::avatars`) the same way the other three collectors already prefix
theirs — extending the "StorageRequest capabilities" entry above, which predates this
standardization and still hand-wrote `$key` with no prefixing at all (uniqueness wasn't yet a
concern when only one extension declared any storage purpose). The grouped-by-extension return
shape `array<string, array<StorageRequest>>` is unchanged — that grouping and the request's own
prefixed id serve different purposes (which extension owns it vs. a globally unique name for it)
and both are worth keeping.

**Why the naming standardization:** `permissions()` originally reconstructed a brand new
`Permission` for every entry, copying `label`/`description`/`callback` over unchanged just to
attach a prefixed id — the same shape `portals()` and `ux()` were about to need too. Once `Portal`
and `UxEntry` were built with a mutable `$id` from the start (so `Manager` could mutate in place
instead of rebuilding), `Permission`'s copy-to-prefix pattern stood out as the odd one out, not the
norm. `StorageRequest::$key` was the last holdout using a different field name entirely for the
same concept (an author-chosen local identifier `Manager` prefixes) — standardizing the name
alongside the mutation pattern means all four value objects read the same way at a glance: "this is
the thing `Manager` prefixes," not "this one's called `key` for historical reasons."

**Why mutate in place instead of keeping value objects immutable:** these objects are already
reconstructed fresh on every request (an extension's `permissions()`/`portals()`/`ux()`/`storage()`
method is called anew each time `Manager`'s collectors run, per the "recomputed fresh every
request, never persisted" reasoning in the Permissions entry above) — there's no shared/cached
instance a mutation could corrupt across requests or across two different callers. Given that,
requiring every collector to hand-copy every other field just to attach one prefixed string was
ceremony with no actual safety benefit.

**Alternatives considered:** Keeping these value objects fully immutable (`readonly` id included)
and having `Manager` construct a prefixed copy, as `permissions()` originally did — rejected once
the pattern had to be written three more times (`portals()`, `ux()`, and now `storageDrivers()`);
the copy step was pure boilerplate, not a safety property worth its repetition. A custom
`ensure()`-backed typed `Collection` per value object, considered earlier the same session for
`UxEntry`/`Portal` and reverted (see the `ChangesUx`/`Ux` entry above) — unrelated to this
decision but the same instinct (add structure defensively) rejected for the same reason: the
mutation these collectors do is trusted, request-scoped, internal `Manager` code, not something
that needs guarding against.

**Status:** Decided & implemented. `Permission::$id`, `Portal::$id` (already mutable from the
Portals entry above), `UxEntry::$id` (already mutable from the `ChangesUx` entry above), and now
`StorageRequest::$id` (renamed from `$key`, dropped the class-level `readonly`). All four of
`Manager`'s collectors mutate in place. `k-extensions/example`'s `Extension::storage()` updated to
`id: 'avatars'`. Verified via tinker: `Manager::storageDrivers()` returns
`kopling-example::avatars` grouped under the `kopling-example` key, same shape as before, just
with the request's own id now prefixed too.

---

## 2026-07-10 — `Ux`: `edit()` to restart chaining, `replace()`/`remove()` to target another entry, `add()` accepts a component's own FQCN

**Decision:** Three additions to `Kopling\Core\Extend\Ux`, all building on the same fluent-builder
shape:

- **`edit(string $id): static`** re-selects an entry already added earlier in the *same* chain
  (matched by its current `$id` — explicit via `as()`, or the default) as the one further calls
  mutate, instead of only ever being able to continue configuring whichever `add()`/`replace()`/
  `remove()` call came last. Throws `\InvalidArgumentException` if no such entry exists in this
  chain — unlike a dangling `after()`/`before()`/`replace()`/`remove()` target, this is never a
  cross-extension/install-order concern, so a miss here is a straightforward author typo, not
  something to degrade gracefully around.
- **`replace(string $id, string $component, array $data = []): static`** and **`remove(string
  $id): static`** each start a new `UxEntry` tagged with a new `Kopling\Core\Ux\UxAction` enum
  (`Add`/`Replace`/`Remove`, `UxEntry::$action`, defaulting to `Add`) targeting another entry's
  already fully-qualified id — the same untouched-by-Manager convention `after()`/`before()`
  already use, not the locally-prefixed convention `as()` uses for an entry's own id. `replace()`
  can still be chained further (`->in()`/`->when()`/etc.); anything left unset on it keeps the
  target's original value, so the common case (swap the component/data, keep the same slot/
  gating) needs nothing beyond the one call.
- **`add()`/`replace()`'s `$component` argument accepts a component's own FQCN** (e.g.
  `Item::class`), not only an already-valid Blade tag string (`"k::portal.navigation.item"`).
  `<x-dynamic-component>` only ever accepts a tag (it compiles straight to `<x-{{ $component }}>`
  — confirmed by reading `Illuminate\View\DynamicComponent::render()`), so a new
  `Kopling\Core\Ux\ComponentTag::resolve()` reverses whichever `Blade::componentNamespace()`
  registration the class falls under back into its tag, called once from `UxEntry`'s constructor
  so `$component` is always render-ready no matter which form was passed in.

`Manager::ux()` is restructured accordingly: instead of building a flat array from every
extension's declared entries, it walks every extension's operations (Core's included, in
`extensions()` order) against one shared `array<string, UxEntry>` registry keyed by id —
`Add` inserts (after prefixing, as before), `Replace`/`Remove` look the target up by its
already-fully-qualified id and mutate/unset it in place. Keying by id and overwriting an existing
key (rather than rebuilding the array) means a `Replace`d entry keeps its original position — PHP
preserves an existing key's position on reassignment — so replacing a component is never
indistinguishable from re-ordering it.

**Why `UxEntry::$component`/`$data` had to stop being `readonly`:** `applyUxReplace()` mutates
them on the *original* `Add`-created entry object already sitting in the registry — the same
"mutate the existing object in place, don't reconstruct" reasoning the id-standardization entry
above already established, extended to these two fields since `replace()` is specifically for
overwriting them post-construction.

**Why a `Replace`/`Remove` target is resolved against the registry as extensions are processed,
not in a separate pass after:** processing every extension's own operations immediately (rather
than collecting all `Add`s first, then all `Replace`/`Remove`s after) means an extension's
`replace()`/`remove()` naturally works against anything an earlier-processed extension (Core
first, then Composer-discovered order) already registered — including something registered
earlier in its *own* `ux()` chain — with no special-casing needed for "local" vs "foreign"
targets. The trade-off, stated plainly rather than left implicit: an extension can only replace or
remove something registered by an extension processed *before* it, never one processed later —
matching how ordinary service-provider registration order already constrains overrides in Laravel
itself, not a new kind of limitation this system introduces.

**Considered and set aside: a component declaring its own default id.** Raised alongside "accept a
FQCN" — could a component like `Item` expose its own id so `as()` becomes unnecessary too? Set
aside: `Item` is a generic, reusable "just a link" component used across many *different*
registrations (Core's Theme link, the example extension's Hello link) — a single id hardcoded onto
the class couldn't serve all of them, so `as()` still has to carry the per-registration identity
regardless. Worth revisiting specifically for a genuinely single-purpose, bespoke component (one
built for exactly one registration), where the component's own identity and the entry's identity
really would be the same thing — not needed yet since no such component exists.

**Alternatives considered:** A separate "operations" value object distinct from `UxEntry`, with
`Manager` translating `Add`/`Replace`/`Remove` into three different object shapes — rejected,
reusing `UxEntry` itself (just tagged with `$action`) needs no translation step and keeps `Ux`'s
internal list a single homogeneous array. Resolving `Replace`/`Remove` targets in a dedicated pass
after collecting every extension's `Add`s — rejected in favor of the single-pass, processed-in-order
approach above, which needs no second data structure and naturally supports targeting an entry
registered earlier in the *same* extension's own chain for free.

**Status:** Decided & implemented. `Kopling\Core\Ux\{Ux,UxEntry,UxAction,ComponentTag}.php`,
`Manager::ux()` (`applyUxAdd`/`applyUxReplace`/`applyUxRemove`). `Core::ux()` and
`k-extensions/example`'s `Extension::ux()` updated to pass `Item::class` instead of the string tag,
proving the FQCN path in real, shipped usage. `edit()` verified via tinker (restart-chaining
mutates the right entry; a missing id throws). `replace()`/`remove()` verified via two synthetic
`ChangesUx` implementations run through the same registry logic `Manager::ux()` uses (a real
cross-extension override scenario doesn't exist in the shipped reference extensions yet, so this
isn't demonstrated in `k-extensions/example` itself — tinker verification stands in for now).

---

## 2026-07-10 — `kopling:extensions:registrations`: a debugging command, not a public API, reads Manager's own collectors rather than re-deriving anything

**Decision:** `Kopling\Core\Console\Commands\ListExtensionRegistrations` (`php artisan
kopling:extensions:registrations {example|kopling/example|core}`) prints everything one installed
extension (or Core) registers — directory conventions present, permissions, portals, storage
requests, Ux slot entries, whether it's `CannotBeDisabled` — each with a runnable-looking usage
example, by calling `Manager`'s own collectors (`permissions()`/`portals()`/`storageDrivers()`/
`ux()`/`conventions()`) and filtering their already-prefixed results down to the one requested
extension's id prefix, rather than re-implementing any of that logic itself.

Two things it reports are necessarily heuristic, not authoritative, and are worded that way in the
command's own output: **route names** are extracted with a regex over the raw `routes/web.php`
source (`->name\('...'\)`) rather than resolved through the real `Router`, because extension routes
aren't required to follow any id-prefixing convention the way views/lang/permissions/portals/ux
are (`k-extensions/example`'s own route is named `example.hello`, not `kopling-example::hello`) —
there's no reliable way to ask "which registered routes belong to this extension" other than
reading the extension's own routes file. **Translation keys** are found by `require`-ing each
`lang/{locale}/*.php` file directly and flattening its array, which works today (that's exactly
what Laravel's own translator does with the same files) but would need updating if translations
ever moved to a different format.

**Why per-extension filtering, not adding an extension-scoped variant to `Manager` itself:** every
existing `Manager` collector already returns results across every installed extension at once,
and that's correct for their real callers (`Gate::define()`, route registration, `SlotResolver`) —
a debugging command filtering the same output down to one id prefix afterward is cheap and needs
no new `Manager` API surface; adding a `Manager::permissionsFor($package)`-style method for every
collector, purely so this one command could avoid an `array_filter()`, would be new production
surface justified only by a dev tool.

**Alternatives considered:** Resolving route names via `Route::getRoutes()` filtered by matching
each route's controller namespace against the extension's own PSR-4 namespace (from `Manifest`) —
more "correct" in principle, rejected as more machinery than a debugging command warrants, and it
would still miss closure-based routes entirely (`k-extensions/example`'s own route is a closure,
not a controller) — the regex approach, while heuristic, actually covers the one route that
exists today and is honest about being a raw-file scan, not the resolved router.

**Status:** Decided & implemented. Verified against both `example` (all three matching forms —
`example`, `kopling-example`, `kopling/example`) and `core`, and against an unmatched name
(clean `FAILURE` exit, no stack trace).

---

## 2026-07-10 — Each Portal's layout defines its own slot map; the shared shell holds only html/head/body

**Decision:** `Kopling\Core\Ux\Portal\Navigation\Side` (hardcoded to `"core::side-navigation"`)
is replaced by a generic `Kopling\Core\Ux\Portal\Slot` (`<x-k::portal.slot name="...">`) that
resolves and renders *any* named slot — no opinion about the markup around it. `Kopling\Core\Ux\
Portal\Layout` (`<x-k::portal.layout>`) is stripped down to just the truly universal html/head/
body wrapper; the header and side-navigation region it used to render itself move out into each
Portal's own layout view. `layouts/admin.blade.php` now composes its own header + `<aside>` +
`<x-k::portal.slot name="core::side-navigation">` explicitly (unchanged in appearance, just no
longer hidden inside the shared shell). `layouts/community.blade.php` is scaffolded as a
genuinely different shape: a top bar (`core::community.topbar`), a sidebar
(`core::community.sidebar`), a main content area with a slot above the routed page content
(`core::community.content-top`), a right rail (`core::community.rail`), and a bottom composer
region (`core::community.composer`) — modeled in the abstract on kopling.convoro.co's own layout
(top nav, left nav + tag list, center feed, right-side widget panels, bottom composer), not a
pixel match.

`Layout`'s constructor also dropped its `Manager`/`Portal`-resolving `portal` prop entirely:
`PortalController` already does `view($portal->layout)->with('portal', $portal)`, so `$portal` is
already in scope inside whichever layout view renders `<x-k::portal.layout>`'s default slot — the
component threading its own copy through was dead weight once nothing inside the shared shell
used it directly anymore.

**Why per-layout slot maps instead of one shared shape every Portal is forced into:** the whole
point of asking for this was that Admin's simple sidebar-and-content shape and Community's
richer, multi-region shape are genuinely different layouts, not the same layout with different
content — forcing both through one shared component with one fixed slot (`core::side-navigation`)
would mean either Community never gets its own regions, or that one component grows a
Community-specific special case, which is exactly the kind of one-off carve-out the rest of this
system has avoided. Making `Slot` generic and moving region composition into each layout view
means a third Portal (the Moderation portal named in the charter) can define a third, completely
different slot map with zero changes to `Slot`/`Layout`/`Manager`/`SlotResolver` — all four already
work for an arbitrary slot name, only the rendering side was needlessly narrowed to one.

**Deliberately not done in this pass (structure first, content later):** `core::community.sidebar`/
`rail`/`content-top`/`composer` have nothing registered into them yet — they render empty, which is
expected, not a bug. Populating the sidebar with real nav (Home/Popular/Following/Bookmarks) and a
tags list is real feature work for a later pass — the tags/categories list in particular is a
dynamic, backend-driven list, not a fit for the same flat link-`Item` model `core::side-navigation`
uses, and needs its own thinking, not a forced fit into this pass. Runtime theme-token overrides
(the "Theme logic" the charter and `admin/theme.blade.php`'s "coming soon" placeholder both point
at) are also out of scope here — this pass only avoids foreclosing on it, by using nothing but
daisyUI semantic classes (`bg-base-*`, `border-base-*`, `text-base-content`) throughout, never a
raw color.

**Found, not fixed, while verifying this:** `Core::ux()`'s Theme entry still points at
`route: 'core::admin.theme'`, a route that no longer exists — `routes/admin.php` and its
dedicated `ThemeController` route registration were replaced by a generic per-Portal route in
`routes/web.php` (`PortalController`, named after the Portal's own id) as part of unrelated,
independent work on the routing/Portal system happening in parallel. `Http/Controllers/Admin/
ThemeController.php` is now an orphaned, unrouted file. Visiting `/admin` as a person granted
`manage-theme` currently throws `RouteNotFoundException` render-side. Confirmed this predates and
is unrelated to the slot-map changes in this entry (verified by revoking `manage-theme` for a test
render, which then renders cleanly) — left as-is rather than guessed at a fix, since resolving it
means deciding whether Theme becomes its own route again or folds into whatever `PortalController`
now renders for the Admin portal's root, and that's a routing-architecture call, not a rendering
one.

**Status:** Decided & implemented for the layout/slot-map shape. `Kopling\Core\Ux\Portal\{Layout,
Slot}.php` and their views, `layouts/admin.blade.php`, `layouts/community.blade.php`. Verified via
tinker (rendering both layouts directly): Community renders all five regions with none throwing on
empty; Admin renders unchanged in shape, with the example extension's "Hello" link still resolving
correctly. The dangling `core::admin.theme` route is a known, separate, unresolved gap — see above.

---

## 2026-07-10 — Theme editor removed for now, not patched around

**Decision:** Pulled everything the dangling `core::admin.theme` route left behind, rather than
patching the route back in: `Core::permissions()`'s `manage-theme` permission, `Core::ux()` and
its `ChangesUx` implementation entirely (it had nothing left to declare once the Theme entry was
gone), `Http/Controllers/Admin/ThemeController.php`, and `Ux/views/admin/theme.blade.php`.
`k-extensions/example`'s own entry dropped its `.after('core::theme')` anchor (the target no
longer exists — would have degraded gracefully to declaration order regardless, per
`SlotResolver`'s missing-anchor rule, but an anchor to something permanently gone rather than
temporarily uninstalled has no reason to stay in the reference implementation). `kopling-landing/
public/extend.html` updated to match: its `'core::theme'` example references replaced with
generic ones, and Section 7/8's prose corrected to describe the actual current architecture (each
Portal layout defines its own slot map via `<x-k::portal.slot>`, not one shared shell/slot every
Portal renders identically) — stale since the layout-scaffolding entry above, caught while fixing
this.

**Why removed instead of re-routing:** the theme editor never had real functionality behind it —
`ThemeController` was already a documented placeholder ("proves the Admin portal's routing/gate/
layout chain resolves end to end," per its own docblock) before its route disappeared in unrelated
Portal-routing work. With no working feature left to gate, keeping a permission, a nav entry, and
two dead files around just to preserve a route name would be scaffolding for its own sake — cheaper
and more honest to remove it now and rebuild deliberately later, once the runtime theme-token
system this was always a placeholder for is actually being designed, than to patch a route back in
for a page that still only says "coming soon."

**Status:** Decided & implemented. Verified via tinker: `Core::permissions()` no longer includes
`manage-theme`; `Manager::ux()` returns only `kopling-example::hello`; the Admin layout renders
cleanly end-to-end with no dangling route reference anywhere. `php artisan
kopling:extensions:registrations core` confirms the same. Coming back to a real theme editor is
future work, not tracked as a gap here — there's no half-built route left to point at.

---

## 2026-07-10 — Admin Portal split into its own extension (`kopling/admin`); Core keeps only Community

**Decision:** The Admin Portal (`Portal(id: 'admin', ...)`, the `access-admin` permission, and
`layouts/admin.blade.php`) moved out of `Core` entirely into a new, ordinary Composer-discovered
extension, `k-extensions/admin` (`kopling/admin`, PSR-4 `Kopling\Admin\`). Its `Extension` class
implements `HasPermissions`/`HasPortals` exactly the way `k-extensions/example` does — nothing
about being "the admin panel" gets special-cased. `Core` now declares only the Community portal
and the `manage-people` permission. The layout view moved as-is (`git mv`) — it only ever used
core-provided `<x-k::portal.layout>`/`<x-k::portal.slot>` and the `$portal` variable
`PortalController` already binds, so nothing inside it needed to change.

IDs shift as a direct, expected consequence of the move: the Admin portal's id is now
`kopling-admin::admin` (was `core::admin`), its permission `kopling-admin::access-admin` (was
`core::access-admin`), its layout view `kopling-admin::layouts.admin`. Registered at the root
composer.json's `require` (`"kopling/admin": "@dev"`) — already covered by the existing
`k-extensions/*` wildcard path repository, so no new repository entry was needed, only
`composer update kopling/admin` (a new path-repo `composer.json` isn't picked up by
`composer dump-autoload` alone — see the existing CLAUDE.md gotcha).

**Real bug found and fixed while doing this, not caused by the split:** `Manager::portals()`
prefixed `Portal::$id` but never `Portal::$permission` — so `Portal::$permission` stayed the bare
local string (`'access-admin'`) while the actual grantable/gated permission was always the
prefixed one (`core::access-admin`, now `kopling-admin::access-admin`). Verified concretely before
fixing: granting a person the real permission and checking `$person->hasPermission($portal
->permission)` returned `false` — the Admin portal's own gate has never actually been passable
since the `permission` field was added to `Portal`. Fixed the same way `ux()` already prefixes a
string `$condition`: `Manager::portals()` now prefixes `$portal->permission` too when it's set,
and `Portal::$permission` is mutable (dropped `readonly`) for the same reason `UxEntry`'s mutated
fields are. Re-verified after the fix: grant passes, revoke denies, both against the real
`kopling-admin::access-admin` permission this time.

**Load order: flagged as open, not solved here.** `Manager::extensions()` guarantees `Core` loads
first; every Composer-discovered extension — `kopling/admin` now included — loads in whatever
order `installed.json` happens to list them, which isn't currently controllable. This matters
concretely for `kopling/admin`: another extension wanting to place something into the Admin
portal's own slots and anchor it `after()`/`before()` one of Admin's own entries, or `replace()`/
`remove()` one, can only do so if `kopling/admin` is processed *before* it (`Manager::ux()`'s
existing ordering constraint — see the `edit()`/`replace()`/`remove()` entry above). While Admin
lived inside `Core`, this was moot: `Core` is always first, full stop. Split out, `kopling/admin`
has no such guarantee. The likely shape of a fix — a `composer.json`-declared load priority (e.g.
`extra.kopling.priority`, `Manifest`/`Manager` sorting discovered extensions by it before
`Core`'s Composer-discovered siblings get instantiated) — is sketched as a TODO directly in
`Kopling\Admin\Extension`'s own docblock, but deliberately not designed or built as part of this
entry.

**Why split now rather than deferring further:** the Admin/Community distinction was already
real (two Portals, two permissions, two layouts) — moving it into its own package now, while only
one thing needs the not-yet-solved load-order guarantee, means the eventual priority mechanism
gets designed against a real, concrete need instead of a hypothetical one, the same reasoning
`RequestsStorageDriver` and `HasPermissions` were added under ("only for capabilities a directory
convention can't express... as real needs prove themselves out").

**Alternatives considered:** Solving load order first, splitting Admin out only once it existed —
rejected; the split itself is independent, low-risk, and mechanical (no code inside the Admin
Portal's own declarations needed to change beyond id prefixes), while priority-loading is a real
design question (composer.json key naming, whether `Core` itself should be expressible as just
"always priority 0" instead of hardcoded-first, how ties are broken) worth its own dedicated pass
rather than rushing alongside a mechanical extraction.

**Status:** Decided & implemented for the split; load order explicitly deferred (see above).
`k-extensions/admin/{composer.json,src/Extension.php,lang/en/permissions.php,
views/layouts/admin.blade.php}`; `Core.php` (Admin portal/permission removed); `Manager::portals()`
(permission-prefixing fix); `Portal::$permission` (mutable). Verified end-to-end: `php artisan
route:list` shows `kopling-admin::admin` at `/admin`; `php artisan kopling:extensions:registrations
admin` lists the new extension correctly; tinker confirms the permission gate now actually passes
when granted and denies when revoked; a guest `GET /admin` over real HTTP still gets a clean `403`,
`GET /` (Community) unaffected.

---

## 2026-07-10 — Runtime theme overrides: a `ChangesTheme` contract, `theme_tokens` for ad-hoc edits, no selection/editor yet

**Decision:** Since Node/Vite can never run on a live Kopling host, "runtime-editable theming"
can't mean compiling a new daisyUI theme — it means overriding a sparse subset of the CSS custom
properties the one compiled `"kopling"` daisyUI theme (`k-core/src/Ux/css/app.css`) already
defines, via a plain inline `<style>` block rendered on every request, layered on top of the
compiled stylesheet.

Built:
- `Kopling\Core\Ux\Theme\Token` — a backed string enum, one case per CSS custom property the
  compiled `"kopling"` theme defines (12 color tokens + 2 radius tokens, nothing more). A
  curated, finite catalog on purpose, not "arbitrary CSS" — keeps a future admin editor to a
  fixed set of meaningful controls, and gives every value a known expected shape to check
  before it ever reaches a `<style>` tag (`Token::matches()` — hex-color regex for color
  tokens, a numeric+unit regex for radius tokens).
- `Kopling\Core\Extension\Contract\ChangesTheme` (`theme(): array<string,string>`, keyed by
  `Token::*->value`) — an extension ships a named theme by implementing this, discovered the
  same `instanceof` way every other capability is. `Manager::themes(): Collection<string,
  array<string,string>>`, keyed by owning extension id (same reasoning as `storageDrivers()`),
  validates every declared key against `Token::tryFrom()` and every value against
  `Token::matches()` — throws immediately on either failure, since this is an extension
  author's own bug caught at declaration time, not a foreign reference that might legitimately
  not exist yet.
- `theme_tokens` migration (`token` string primary key, `value` string) — ad-hoc, per-token
  overrides layered on top of whatever theme is installed. Empty today; nothing writes to it
  yet (the interactive editor is explicitly deferred), but `Theme::css()` already reads from it.
- `Kopling\Core\Ux\Theme::css()` (the class existed as an empty stub since early scaffolding;
  this is its first real implementation) — merges every installed `ChangesTheme` extension's
  tokens, then overlays `theme_tokens` rows on top, and renders `:root[data-theme="kopling"]
  {--token:value;...}`. Wired into the one shared `layouts/partials/head.blade.php`, so it's
  live on every Portal (Community, Admin, any future one) with no per-Portal wiring — the same
  "one shared spot, true for everyone automatically" reasoning the layout-scaffolding entry
  already established for chrome shared across Portals.
- `kopling/theme-midnight` — a real extension (not a fixture) shipping a dark palette via
  `ChangesTheme`, proving a "theme" is just this capability like any other, not a special-cased
  concept. Deliberately overrides only the 12 color tokens, leaving both radius tokens alone —
  proof the merge is genuinely sparse, not an all-or-nothing full-theme swap.

**Why `:root[data-theme="kopling"]` as the override selector, specifically:** confirmed by
reading the actual compiled CSS output (`public/build/assets/*.css`) that the compiled theme's
own rule is a bare `[data-theme=kopling]` attribute selector — specificity (0,1,0).
`:root[data-theme="kopling"]` combines a pseudo-class with the same attribute selector for
(0,2,0), reliably beating the compiled rule regardless of where in `<head>` the override
`<style>` tag ends up, rather than depending on source-order alone (which would still have
worked here, but shouldn't be the thing load-bearing).

**Two trust levels, two different failure behaviours for what's structurally the same
validation:** `ChangesTheme`-declared tokens are checked once, in `Manager::themes()`, and throw
on failure — code, author's own bug, fail fast in dev. `theme_tokens` rows are checked again, in
`Theme::css()`, on every single page load, and a row that fails validation is silently skipped
rather than thrown on — once an admin editor exists, this runs against something a real person
submitted, and one bad row should never be able to take the whole site down; falling back to
whatever the next layer down says for that one token is the correct failure mode, not a 500.
Verified concretely: a deliberately malformed `theme_tokens` row (`--color-primary` set to
`javascript:alert(1)`) was silently ignored, `--color-primary` still rendered Midnight's own
valid value.

**Explicitly not built, on purpose:** the interactive editor (live preview, an admin UI) —
deferred by direct instruction, not forgotten. Selection between multiple simultaneously-
installed `ChangesTheme` extensions — `Manager::themes()` just merges every installed theme's
tokens together in `extensions()` order, last write wins on overlap; correct and sufficient
while at most one theme extension is ever installed, a genuinely unsolved problem the moment a
second one exists (no different in kind from the extension-load-order gap already flagged for
`kopling/admin`).

**Alternatives considered:** Storing overrides as one JSON blob instead of a `token`/`value`
table — rejected, a flat table is trivial to list/edit per-token once an editor exists and there's
only ever one community per install, so no document-shape benefit to gain. Compiling a genuinely
new daisyUI theme per community (e.g. shelling out to a build step per request or per save) —
rejected outright, directly contradicts the standing "Node/Vite never runs on a Kopling host"
architecture rule. Letting `ChangesTheme` values through unvalidated since they're "trusted,
code-authored" — rejected: these still get interpolated raw into a `<style>` tag, and a
supply-chain-compromised or simply buggy extension is exactly the scenario basic shape validation
is cheap insurance against.

**Status:** Decided & implemented for the non-interactive half, as scoped. `k-core/src/migrations/
2026_07_10_000002_create_theme_tokens_table.php`, `k-core/src/Ux/Theme.php`, `k-core/src/Ux/Theme/
Token.php`, `k-core/src/Extension/Contract/ChangesTheme.php`, `Manager::themes()`,
`layouts/partials/head.blade.php`, `k-extensions/theme-midnight/*`. `kopling:extension:
registrations` updated to know about `ChangesTheme` too (a real gap: it predated this contract and
listed Midnight as implementing nothing). Verified end-to-end: `Theme::css()` renders empty with
nothing installed; renders Midnight's full color palette with radius tokens correctly absent once
installed; a `theme_tokens` row correctly overrides a Midnight value; a malformed `theme_tokens`
row is correctly and silently ignored rather than corrupting the page; the override is confirmed
present, in the right position, in a real rendered Community page. Not built: the admin editor,
theme selection among multiple installed themes, and `color-scheme` (native browser UI like
scrollbars/form controls won't flip dark under Midnight yet — a real gap, not urgent).

---

## 2026-07-10 — Flagged for later: every discovered extension is always enabled, no disable toggle exists

**Status quo, confirmed deliberate for now:** `Manager::extensions()` instantiates and treats as
active every extension Composer discovers (`"type": "kopling-extension"`), unconditionally — there
is no "enabled" flag anywhere, for any extension, including `kopling/admin` and
`kopling/theme-midnight` from earlier today. Fine as things stand: every installed extension today
is something deliberately added to root `composer.json`'s `require` — installed already implies
wanted. `CannotBeDisabled` (see its own entry above) was built specifically anticipating this gap:
it guards a future toggle, the toggle itself was never in scope.

**Why this is worth its own entry rather than just the code comment:** the extension roster grew
from one dummy (`kopling/example`) to four real ones in a single session (`kopling/admin`,
`kopling/theme-midnight`, plus `kopling/reactions`) — the moment "every installed extension is
always active" stops being an obviously-fine simplification is exactly the moment a second theme,
or an extension a host wants installed-but-dormant by default, shows up. Recording it now, before
that pressure exists, rather than rediscovering it under time pressure later.

**Not designed here, only flagged:** where "enabled/disabled" would persist (a settings-style
table, similar in shape to `theme_tokens`), how `Manager::extensions()` would filter by it without
breaking the memoized-instantiate-once shape it has today, and how it interacts with the also-not-
yet-solved extension load-order problem (flagged in the Admin-split entry above) -- all open.

**Status:** Not implemented, intentionally. Tracked in code (`Manager::extensions()`'s own
docblock) and here so it surfaces again once a real need for it shows up.

---

## 2026-07-10 — Community's card feed extracted into granular `Ux/Card/*` components

**Decision:** The hand-written card markup in `layouts/community.blade.php` (built the same
session, immediately prior) is now eleven small components under `Kopling\Core\Ux\Card\*`
(`<x-k::card.*>`, following the same core-only directory-mirroring convention as `Ux/Portal/*`):
`Card` (the `card`/`card-body` shell), `Top` (header row), `Body` (title/text area), `Footer`
(bottom row, reuses daisyUI's own `card-actions` part), `Avatar`, `Author`, `Timestamp`, `Tag`,
`Control` (the per-card "..." actions button), and two content-agnostic layout primitives, `Row`/
`Column` (flex arrangement, no props, used both inside `Top` for the author/timestamp grouping and
inside `Footer` for the reply/reaction counts). `community.blade.php` now composes these instead of
writing the markup directly.

**Why this decomposition, not fewer/coarser components:** matches the charter's own stated
extensibility model directly — "Keep Blade partials small. Partial granularity IS the
extensibility budget" — rather than introducing a new principle. A single monolithic `Card`
component would have been simpler today but would force anyone wanting a slightly different card
(a different control, no timestamp, an extra badge) to fork the whole thing instead of recomposing
smaller pieces. `Row`/`Column` deliberately take no props and know nothing about cards
specifically — proven as genuinely general (not just "footer helpers") by reuse inside `Top` for
the author/timestamp stack, the same session they were written.

**Where `ml-auto` lives, and why it matters:** on `Control` itself, not on `Tag` or imposed by
`Top`'s own markup — matches the actual request ("row containing avatar, author, timestamp, tag
and, aligned right, a card control"): everything up to and including `Tag` flows left-to-right
normally: `Control` is the one thing that floats to the far right, and it earns that behavior by
carrying it itself rather than `Top` special-casing "whatever's last."

**Deliberately not built:** `Control` renders a plain, non-functional icon button — no dropdown,
no menu items, no htmx attributes. There's no real per-post action (edit, delete, report) to hang
off it yet; wiring one in before a real action exists would mean inventing fake interactivity.
daisyUI's own `dropdown` component (popover-API variant) is the documented path once one does.

**Status:** Decided & implemented. `k-core/src/Ux/Card/*.php` (11 classes) and their mirrored
`k-core/src/Ux/views/card/*.blade.php`; `layouts/community.blade.php` updated to compose them.
Verified: cleared compiled views, re-fetched the live Community page over real HTTP, confirmed all
three placeholder cards render with the exact same structure as the pre-extraction inline markup
(avatar, author/timestamp column, tag, control button all present and correctly positioned; title/
excerpt; reply/reaction counts) — a pure refactor, no visual/structural change intended or
observed.

---

## 2026-07-10 — Card's header/body/footer become genuinely extensible, bound to a real `Moment` model, via two small mechanisms — not one growing `SlotResolver`

**Decision:** `Card`'s previously-hardcoded children (built the same session, immediately prior)
now go through the extension mechanism, but split deliberately across two small, separately-scoped
pieces rather than one class absorbing every new concern:

1. **Child-slot composition — the existing `Ux`/`Manager::ux()` pipeline, completely unchanged.**
   `Top`/`Body`/`Footer`'s own regions are just slot names (`core::card.header`, `core::card.body`,
   `core::card.footer`) — the same fully-qualified-string convention `core::side-navigation`
   already uses. An extension targets them with the exact same `.add()`/`.replace()`/`.remove()`/
   `.after()`/`.before()`/`.when()` calls already known from `ChangesUx`. Zero code changed in
   `Manager::ux()` to make this work — it was already slot-name-agnostic.
2. **Component-declared defaults — co-located on the component itself, not centralized.** `Top`/
   `Footer`/`Body` each gained a `public const SLOT` and `public static function defaults(Ux $ux):
   void` appending their own default children onto a builder passed in. `Core::ux()` is now a
   *thin composition point* — `Top::defaults($ux); Footer::defaults($ux); Body::defaults($ux);` —
   not a dumping ground; the actual declarations live on each component class. Directly answers
   "each component needs a class and a definition of what child components go" — open `Top.php`,
   its defaults are right there, not buried in `Core`.

**Explicitly dropped, not deferred:** a third mechanism for wholesale component replacement (a
`ReplacesComponents` contract + `Manager::components()` collector + `ComponentRegistry`, resolving
`Card` itself via `<x-dynamic-component>` instead of a hardcoded tag) was designed and then
rejected during review — the contract-plus-collector shape didn't sit right. Unlike the load-order
or theme-selection gaps flagged earlier this session, this isn't tracked as a TODO to resume from
this sketch — it's out of scope for now, to be redesigned from scratch if a real need for it shows
up. `Card` stays a plain, directly-tagged component; only what's *inside* it is extensible today.

**`Context`, not a loose `array $data`, for the dynamic/model-bound channel.** A new
`Kopling\Core\Ux\Context` (`subject`, `actor` — `actor` defaults to `Auth::user()`, `null` for a
guest) is constructed once per rendered `Moment` and passed unchanged from `Card` down through
`Top`/`Body`/`Footer` to every slot-rendered leaf. `UxEntry` gained a matching mutable
`public ?Context $context = null`; `SlotResolver::resolve()` gained an optional `?Context $context`
parameter, set on every surviving entry right before rendering (`null` for slots that aren't bound
to anything, like `core::side-navigation`). Every leaf (`Avatar`, `Author`, `Timestamp`, `Control`,
new `Content`) now uniformly takes **both** `array $data` (static, author-declared config — still
useful, e.g. a future reactions button's own settings) **and** `?Context $context` (the dynamic
binding) — two clearly separate channels, always both present, rather than conflating "what this
registration configured" with "what instance this is rendering for" into one array. `subject` is
typed `mixed`, not `Moment` — only one bound-model type exists today, so a shared interface would
be invented ahead of a real second one (same reasoning as the deferred extension-load-order and
theme-selection gaps).

**No `tag` on `Moment` at all.** Tagging is a future extension's own concern, not core's — the
`moments` migration has no `tag` column, and `Top::defaults()` no longer registers a `Tag` child by
default. The `Tag` component class itself is kept (a generic, reusable "small label" primitive a
real tags extension can reuse) — just not part of Core's own header defaults anymore.

**`Author`+`Timestamp` are no longer visually grouped.** They were stacked via `Column` before;
now they're two independent, individually-orderable entries in the flat `core::card.header` list
(so an extension can insert something *between* them), rendering inline instead of stacked — a
stated trade-off, not an oversight. `Row`/`Column` aren't deleted, just unused by Card's own
defaults for now.

**`Footer` ships with zero default children on purpose.** No fake reply/reaction counts —
`k-extensions/reactions` (still a bare stub, no contracts implemented) is the real future consumer
of `core::card.footer`. Shipping a placeholder count now would be exactly the "dummy data" this
redesign was meant to get away from.

**The `Moment` model:** `Kopling\Core\Content\Moment` (`HasUuids`, matching `Person`/`Group`;
`belongsTo(Person::class)`), migration `2026_07_10_000003_create_moments_table.php` — `id`,
`person_id` (FK → people, cascade delete), `title`, `body`, timestamps. `community.blade.php` now
queries `Moment::with('person')->latest()->get()` instead of a hardcoded array, building one
`Context` per moment.

**Status:** Decided & implemented. `Kopling\Core\Ux\Context`, `UxEntry::$context`,
`SlotResolver::resolve()`'s new param, `Core::ux()`, `Kopling\Core\Ux\Card\{Card,Top,Body,Footer,
Content,Avatar,Author,Timestamp,Control}.php` and their views, `Kopling\Core\Content\Moment` +
migration, `community.blade.php`. Verified: real HTTP render of the Community portal with two
seeded `Moment` rows shows correct avatar initials, author name, real relative timestamps (via
`created_at->diffForHumans()`), title/body, empty footer, no tag anywhere. Extensibility proven via
tinker with a synthetic `ChangesUx` implementor targeting `core::card.header`: its entry appears
correctly positioned (`.after('core::author')` landed it between author and timestamp) and its
`$entry->context->subject` is confirmed to be the exact same `Moment` instance every core entry
received. The `SLOT`/`defaults()`/`data`+`context` pattern is the template for `Portal`/`Slot`/
`Item` and anywhere else later — not applied there in this pass.

---

## 2026-07-10 — Community index goes live: htmx polling first, SSE-over-FPM/Reverb deliberately not built yet

**Decision:** New `Moment`s now appear on the Community index without a manual reload, via plain
htmx polling — not SSE, not Reverb. A small poller element (`community/poll.blade.php`) declares
`hx-trigger="every 12s"` and `hx-swap="none"` — idle by default, it does *nothing* to the DOM on
its own. Every 12s it hits `LatestMomentsController::check()` (`{portal}/moments.latest`); finding
nothing newer than the `since` cursor it carries, that returns a bare `204 No Content` — htmx's own
documented "don't touch the DOM" signal, so idle polling has no view to render and no markup for
the client to parse for a response that would've changed nothing anyway. Finding something, the
response is the "N new moments — click to view" banner (`community/new-moments.blade.php`) *plus*
an `HX-Reswap: outerHTML` response header — overriding the poller's own declared `hx-swap="none"`
for that one response only, which is what actually lets the banner replace the poller despite what
the poller itself declared. Clicking the banner (it declares its own `hx-swap="outerHTML"` directly,
no override needed there) hits `LatestMomentsController::load()` (`{portal}/moments.load`), which
renders the actual new moments — through the exact same `community/moment.blade.php` partial the
initial page load already uses, so any Card extension's header/footer additions show up identically
either way — as an `hx-swap-oob="afterbegin"` block prepended into `#moments-feed`, alongside a
fresh poller (back to `hx-swap="none"`, idle again) with `since` advanced to the newest of what was
just loaded.

**Why `204`, not `304`, for the "nothing new" case — raised directly during review, worth being
precise about since they're easy to conflate:** `304 Not Modified` belongs to HTTP's conditional-
GET/caching protocol specifically — the *client* sends `If-None-Match`/`If-Modified-Since`
(validators the browser generates automatically, but only against a response the server previously
marked cacheable via `ETag`/`Last-Modified`), and the server compares and returns 304 if unchanged.
It answers "has *this* cached resource changed", for a stable, revalidatable URL. This poll's URL
isn't that: `since` is baked into the query string, so it's a genuinely different URL — and a
genuinely different question ("anything after *this* timestamp?") — on every request; there's no
single cached resource being revalidated. `204`, by contrast, is just this server's own application
logic (`count === 0`) deciding there's nothing to report — no validators, no caching protocol, and
(this is the part that actually rules 304 out even if you wanted it) a 304 response is spec-required
to carry no body either way, so it couldn't deliver the banner in the "found something" branch
regardless of which status code the empty branch used. Building genuine 304 support here would mean
implementing real conditional-GET validation to solve a problem `204` already solves outright, for
no additional benefit.

**Why polling, not SSE, given SSE was the original instinct:** SSE doesn't strictly need a separate
daemon — it can run "over FPM," the request simply staying open and writing `data: ...\n\n` frames
in a loop. But that means one PHP-FPM worker held open for as long as each visitor's tab stays on
the page, and this install's actual hosting profile today (confirmed by reading `.env`/
`composer.lock`: `QUEUE_CONNECTION=sync`, no Redis, no Reverb, no broadcasting driver installed at
all) is exactly the "shared hosting" tier `kopling-landing/CLAUDE.md`'s own stated posture already
names: *"runs on shared hosting (sync queue, polling/SSE-over-FPM fallback, cron scheduler) and
shines on real infra (Reverb, real queues)."* SSE-over-FPM sits at the edge of the fallback tier
(real worker-pool-exhaustion risk with more than a handful of concurrent open tabs, plus reverse-
proxy response-buffering footguns that silently break streaming unless explicitly disabled) — not
the right first thing to reach for on infra that has no daemon and no capacity plan for either.
Polling has none of that: every check is a normal, complete, fast request/response, nothing held
open, zero new server config.

**Why a "click to load" banner, not auto-inserted cards:** silently shoving new cards above
whatever someone's currently reading is jarring and invites accidental clicks, and cuts against
the project's own stated values (`kopling-landing/CLAUDE.md`: "Engagement is a result of people
enjoying each other — never a target to optimize (no engagement-bait mechanics)"). A calm,
person-initiated "N new" indicator was chosen over an always-live auto-append.

**Why `created_at`, not an id, is the cursor:** `Moment` uses `HasUuids` — UUIDs aren't
sequentially sortable, so "everything created after what I've already seen" only means anything as
a time comparison.

**Why `since` never advances during idle polling, only at `load()` time:** nothing about the poller
actually needs to change while nothing new exists, so there's nothing to re-render — nothing to
advance past. `since` only ever needs to move once something's genuinely been loaded, and `load()`
already re-emits a fresh poller (via `@include('core::community.poll', ...)`) with `since` advanced
to the newest of what it just rendered — the cursor advances for free as a side effect of that one
real state change, with zero client-side JS and no need to track anything across polls.

**A per-card partial had to exist before any of this could work honestly.** The `@foreach` in
`community.blade.php` used to build `<x-k::card.card>` inline; extracted to `community/
moment.blade.php` first, so the polling response and the initial page load are provably rendering
the same thing, not two independently-maintained copies that could quietly drift.

**Explicitly not built, and why it's not tracked as a resolvable TODO the way load-order/theme-
selection are:** SSE-over-FPM and Reverb-backed push are both real, known upgrade paths from here
(swap `hx-trigger="every 12s"` for htmx's `sse` extension driven by a similar long-lived endpoint,
or a Reverb-broadcast event once that infra exists) — but pursuing either without a concurrency
plan (a max simultaneous-connection cap, disabling reverse-proxy buffering, the admin capability-
detection dashboard `kopling-landing/CLAUDE.md` already calls for) would be trading a working,
low-risk mechanism for a fragile one, for latency this feed doesn't need yet.

**Status:** Decided & implemented. `k-core/src/Http/Controllers/LatestMomentsController.php`,
`k-core/src/Ux/views/community/{moment,poll,new-moments,loaded}.blade.php`,
`k-core/routes/community.php` (`moments/latest`, `moments/load`), `community.blade.php` (shared
partial, `$since`, `#moments-feed` id). Verified over real HTTP end to end, including after the
`204`/`hx-swap="none"`/`HX-Reswap` revision: an idle poll returns a genuine `204` with a zero-byte
body; a `since` before existing rows returns `200` with the correct "N new" banner *and* the
`HX-Reswap: outerHTML` header present; the full page still renders the poller with `hx-swap="none"`
correctly; clicking `load` prepends the new moments (rendered through the full `Card`/`Top`/`Body`/
`Footer` pipeline, not a simplified copy) and returns a poller with `since` advanced to the newest
of them; creating a genuinely new `Moment` afterward and polling with that advanced cursor correctly
reports only the one truly-new moment, not re-surfacing the ones already loaded — the
cursor-advancement cycle holds up across a real create-and-poll round trip, not just a single
request in isolation.

---

## 2026-07-11 — Login/registration is core-owned scaffolding + two extension points (`ValidateLogin`, `AttemptLogin`); no login method is built in

**Decision:** `Kopling\Core\Authentication\Controller\LoginController` owns the `/login` GET+POST flow
(and `/logout`); `RegistrationController` owns `/register` (GET only so far). Neither controller
knows what "credentials" means. `login()` dispatches two events from
`Kopling\Core\Authentication\Event`:

- `ValidateLogin(Request $request)` — dispatched via `$events->until()` before the throttle
  check; a listener (e.g. a captcha check) vetoes by throwing directly from inside itself. Its
  return value is discarded — there's nothing for it to carry back, only pass/fail.
- `AttemptLogin` — mutable, carries the outcome: `?Person $person`, `ValidationException $e`
  (defaulted in the constructor to `ValidationException::withMessages([])`, never null), and
  fluent `succeeded(Person $person): self` / `failed(ValidationException $e): self` mutators. `
  LoginController::attemptLogin()` constructs one instance, dispatches it with plain `dispatch()`
  (not `until()`), and returns that same local reference regardless of what any listener
  returned. `login()` checks `$event->person`: if set, it calls `Auth::login()` itself and
  completes the session; otherwise it increments the throttle counter and rethrows `$event->e`.

Both routes moved from an unprefixed block in `k-core/routes/web.php` into
`k-core/routes/community.php` — they're now part of the `community` portal's own route group,
registered as `core::community/login`, `core::community/login.attempt`,
`core::community/register`, `core::community/logout` (URL paths unchanged; only the route
*names* gained the portal prefix). `RedirectHtmxUnauthenticated` was updated to check/redirect to
`core::community/login` instead of the bare `login` it used as a placeholder before.

**Why an event pair, not a single event:** the two events answer genuinely different questions.
`ValidateLogin` is a pure gate (continue or don't) with nothing to hand back. `AttemptLogin` has
to hand back *who*, or a reason it didn't work — a bare boolean can't carry either, and a thrown
exception can't carry a successful `Person` back out. Splitting them means a listener only
implements the shape it actually needs, and core never has to guess which case it's in.

**Why `AttemptLogin` uses `dispatch()` + a locally-held reference, not `until()`'s return value:**
the first version relied on `until()`'s return value directly (`return $this->events->until(new
AttemptLogin($request));`), typed `bool|ValidationException` and later `AttemptLogin` with a
non-nullable return type. Both broke immediately — verified via `php artisan tinker` — with a
`TypeError`, because `until()` returns `null` the moment nothing is listening (true today; no
login-method extension exists yet), and a listener mutating the event object it received doesn't
make `until()` itself return that object unless the listener remembers to explicitly `return`
it. Holding `attemptLogin()`'s own reference to the `AttemptLogin` it constructs and returning
that, independent of what `dispatch()` reports, removes the crash *and* the "did the listener
remember to `return $event`" footgun in one change — no listener authoring mistake can make this
method return the wrong type.

**Why the `ValidationException` fallback on `AttemptLogin::$e` is an empty `withMessages([])`,
not a real message:** confirmed via tinker that this produces zero PHP errors (a genuinely empty
error array, HTTP 422) rather than a crash, but it's a known, deliberately deferred gap — with no
login-method extension installed, a login POST silently redirects back with no visible error at
all. Flagged to revisit once `kopling/auth-password` (or equivalent) exists to make "no login
method is installed" a real, visible message instead of a silent no-op.

**Why the throttle key dropped the identifier field entirely:** `ThrottlesLogins::throttleKey()`
originally read `$request->input($this->username())` — `username()` assumed every login method's
form calls its identifier field the same thing (`email`), which is exactly the assumption
`AttemptLogin`'s opaque-request design was built to avoid. `username()` was removed along with
`credentials()`; `throttleKey()` now keys by `$request->ip()` alone. Coarser (keyed per-IP, not
per-identifier-attempted), but makes zero assumptions about what any given login method's request
shape looks like.

**Why the routes moved into `community.php` despite the naming cost:** login/register are
arguably portal-agnostic (any current or future Portal could reuse the same login page) — keeping
them in `web.php`'s unprefixed block was defensible on those grounds, and was flagged as such
before this change. Moved anyway, explicitly accepting that `route('login')`/`Route::has('login')`
no longer resolve and that Laravel's stock `auth` middleware `redirectTo()` convention (which
looks for a route literally named `login`) no longer applies here. This also finally closes the
trade-off logged in the "htmx auth-wall" entry above ("Revisit the literal `/login` fallback once
a real login route lands") — `RedirectHtmxUnauthenticated` now checks a real, existing route name
(`core::community/login`) instead of falling back to a hardcoded string on every request.

**Trade-off accepted / not yet resolved:**
- No login-method extension exists yet (`kopling/auth-password`, discussed but not started) — the
  two events above have no real listener anywhere, so every login attempt today fails with an
  empty-message `ValidationException` (see above).
- There is no Kopling-native mechanism for an extension to *register* a listener for
  `ValidateLogin`/`AttemptLogin` at all yet — doing so today means reaching for Laravel's own
  `Event::listen()` directly, which cuts against the standing "extensions code against Kopling's
  own contract, never against Laravel directly" principle (`extend.html` Section 3). This is the
  same gap `extend.html`'s open-items list already names ahead of time (`Kopling\Extend\*`,
  "a wrapped, blessed subset of Laravel primitives... events... Not yet populated") — now with a
  concrete, real caller waiting on it instead of a hypothetical one.
- `RegistrationController` has no POST handling yet — only `LoginController` was carried through
  this event-pair treatment so far.

**Status:** Decided & implemented for the scaffolding (routes, controllers, events, throttling).
Not yet a working login — see trade-offs above.

---

## 2026-07-11 — Core's views moved to `k-core/views/` (the same top-level convention every extension already uses); its Manager package key changed from the literal `'core'` to its real Composer name `'kopling/core'`, so its namespace is now `kopling-core::` (was `core::`)

**Decision:** `k-core/src/Ux/views/` moved to `k-core/views/`, matching the directory-convention
layout `Manager::conventions()` already expects of every extension (`views/`, `lang/`,
`migrations/`, `routes/`, `css/`, `js/` at the package root). `ServiceProvider::boot()`'s
hardcoded `$this->loadViewsFrom(__DIR__.'/../Ux/views', 'core')` call is gone — Core's views now
load exactly the way an extension's do, through the per-package `foreach ($manager->extensions()
as $package => $extension)` loop already in `boot()`, keyed off `Manager::conventions($package)`.

That loop derives the loaded namespace from `Manager::id($package)`, which strips the vendor
segment by turning `/` into `-` (`kopling/example` → `kopling-example`). Core's entry in
`Manager::extensions()` was `'core' => new Core()` — a literal, hand-picked string, not Core's
real Composer name (`kopling/core`, per `k-core/composer.json`) — so `id('core')` was a no-op and
Core's namespace stayed the bare `core`. Changed the key to the real `'kopling/core'` (updating
`Manager::path()`'s matching special case, and `ServiceProvider::boot()`'s `$package !== 'core'`
guard that keeps the Blade `k` component prefix from being overwritten by the loop) so Core no
longer gets bespoke treatment here either — its namespace is now `kopling-core`, derived the exact
same way `kopling-admin`, `kopling-discussions`, etc. already are for every other extension.

Every hand-written `'core::...'` string literal that isn't one of `permissions()`/`portals()`/
`ux()`'s auto-prefixed local ids (view names passed to `view()`/`@include()`, route names passed
to `route()`/`Route::has()`, translation keys passed to `__()`, Blade slot `name="..."` attribute
values, and `SLOT` class constants like `Sidebar::SLOT`/`Footer::SLOT`/`Top::SLOT`) was updated to
`'kopling-core::...'` by hand across `k-core/src` and the four in-repo extensions that already
referenced it (`admin`, `auth-email-password`, `discussions`, `example`) — 33 files, ~57
occurrences. `Core::portals()`/`permissions()` themselves needed no edits: they already declare
local, unprefixed ids (`'community'`, `'manage-people'`) and let `Manager` prefix them, so they
picked up `kopling-core::` automatically once the package key changed. The Blade component prefix
(`x-k::*`) is untouched — that's a separate, permanently-short prefix registered directly via
`Blade::componentNamespace('Kopling\\Core\\Ux', 'k')`, never derived from the package id.

**Why now, not left as the one remaining special case:** the views move made the inconsistency
concrete rather than cosmetic — routing Core's views through the same convention-based loop every
extension's views already go through only works cleanly if Core's own package key resolves the
same way theirs do. Leaving `'core'` as a hand-picked literal while every other id derivation goes
through `Manager::id()` would have meant either keeping a second, parallel `loadViewsFrom` call
just for Core (defeating the point of the move) or quietly special-casing the loop further.
Confirmed via `php artisan route:list` and `tinker` (`Manager::permissions()`/`portals()`,
`view('kopling-core::auth.login')->render()`, `__('kopling-core::auth.log_in')`) that routes,
permissions, portal ids, view resolution, and translation resolution all now consistently resolve
under `kopling-core::`, matching how a real extension resolves under its own vendor-prefixed id.

**Trade-off accepted:** `k-core/migrations` and `k-core/routes/web.php` are already loaded twice
today — once by `ServiceProvider::boot()`'s own explicit `loadMigrationsFrom`/`loadRoutesFrom`
calls, and again by the same per-package loop this change routes views through, since both
directories already exist at Core's package root and satisfy `Manager::conventions()` on their
own. Pre-existing, not introduced by this change, and out of scope here — left as-is; the views
move only removes the one explicit call that *was* necessary (views wasn't at the package root
before), it doesn't touch the other two.

**Status:** Decided & implemented. `extend.html` (a separate repo, `../kopling-landing`) still
documents Core's slot examples as `core::side-navigation` / `core::community.*` — flagged, not
updated here since it's outside this repository.

---

## 2026-07-11 — `RegistrationController` carried through the same `Validate*`/`Attempt*` event-pair treatment as `LoginController`; `kopling/auth-email-password` now handles registration too, with `AttemptRegistration` carrying a possibly-unsaved `Person`

**Decision:** `RegistrationController::register()` now mirrors `LoginController::login()`:
`ValidateRegistration(Request $request)` dispatched via `$events->until()` first (same pure
gate/veto shape as `ValidateLogin` — no real listener hooks it yet, same status `ValidateLogin`
itself is still in), then `AttemptRegistration` (`?Person $person`, `ValidationException $e`
defaulted to `withMessages([])`, `succeeded()`/`failed()` fluent mutators — same shape as
`AttemptLogin`) constructed and dispatched via plain `dispatch()`, with `register()` holding its
own local reference and checking `$event->person` — identical reasoning to `AttemptLogin` on why
`until()`'s own return value isn't used. `validator()`/`create()` (the hardcoded
name/email/password-against-`Person` scaffold from the previous entry) are gone from Core
entirely.

**The one real difference from `AttemptLogin`:** `AttemptRegistration::succeeded(Person $person)`
does not mean "this person is saved" — login only ever authenticates an already-persisted
`Person`, but registration has to create one. The `Person` a listener hands to `succeeded()` can
be a fresh, unsaved instance; `register()` calls `$event->person->save()` exactly once, after
`dispatch()` has run every registered listener, not inside any listener itself. This means a
listener registered *after* the one that actually built the `Person` (a hypothetical future
defaults/preferences extension, say) can still read and mutate the same `$event->person` instance
in place before it's written — setting a locale, seeding a preference row's foreign key, etc. —
without needing its own separate event or a second database write. Documented directly on
`AttemptRegistration`'s own docblock since it's the one place this design reads as surprising
next to `AttemptLogin`'s.

**`kopling/auth-email-password` extension changes:** added `RegistrationForm` (mirrors
`LoginForm` exactly — a `Context`-aware Blade component with `data`/`context` props) registered
into the `kopling-core::auth.registration-form` slot, and `AttemptPasswordRegistration` (mirrors
`AttemptPasswordLogin`) listening to `AttemptRegistration`. It validates `name`/`email`
(`unique` against `Person`)/`password` (`confirmed` + `Password::defaults()`) via
`Validator::make()->fails()` — deliberately not `->validate()`, same reasoning as
`AttemptPasswordLogin`: a thrown exception from inside a listener would abort `dispatch()`'s
loop before any listener registered after it gets a chance to run, so failure is communicated
back through `$event->failed()` instead. On success it calls `$event->succeeded(new
Person($validator->validated()))` — deliberately `new Person(...)`, not `Person::create(...)`,
since creating this `Person` isn't this listener's job to finish, only to start.

**Bug caught before it shipped:** `Manager::ux()` keys its aggregate registry by each entry's
fully-qualified id globally (`$registry[$entry->id] = $entry` in `applyUxAdd()`), not scoped per
slot. `LoginForm`'s existing entry used `.as('form')`; giving the new `RegistrationForm` entry
the same local id would have collided on the same prefixed id
(`kopling-auth-email-password::form`) despite targeting a different slot, silently dropping
whichever `add()` ran second from the registry. Renamed both to `login-form`/`registration-form`.

**Why the registration-page slot needed no new mechanism:** `Kopling\Core\Ux\Portal\Slot`
already `@foreach`s every `UxEntry` registered into a slot, ordered by `after()`/`before()` — the
exact same mechanism `kopling-core::card.header` already uses to stack `Avatar`/`Author`/
`Timestamp`/`Control` from independent registrations. A future SSO/OAuth2 extension can add its
own button into `kopling-core::auth.registration-form` (and `...login-form`) the same way,
anchored against `kopling-auth-email-password::registration-form` — confirmed via
`Manager::ux()` in tinker that both of `auth-email-password`'s entries resolve to distinct,
correctly-slotted ids after the id-collision fix above. In practice an OAuth extension would most
likely own its own redirect/callback routes entirely and never dispatch `AttemptRegistration` at
all — but nothing prevents a future registration method that *does* want to reuse Core's shared
`POST register` pipeline from listening to the same event, the same way a second `AttemptLogin`
listener already could.

**Verified end-to-end via `tinker`** (real `RegistrationController::register()` call wrapped in a
rolled-back `DB` transaction, not a browser): a valid submission creates a `Person` with a
bcrypt-hashed password, fires `Registered`, logs the person in (`Auth::check()` true), and
redirects; an invalid submission (bad email, mismatched/short password) throws the expected
`ValidationException` with per-field messages and creates no `Person` row.

**Status:** Decided & implemented. Registration and login are now symmetric, both extension-owned
via the same event-pair pattern. Not yet browser-verified by Daniël — see standing note that UI
changes get a human click-through, not just tinker.

---

## 2026-07-11 — `kopling/auth-email-password` adds guest-only Log in / Register links into `kopling-core::community.topbar`

**Decision:** Added `Kopling\AuthEmailPassword\AuthLink`, a small generic `label`/`route`/
`variant` component — the same "one data-driven component, not one class per link" shape as
`Kopling\Core\Ux\Portal\Navigation\Item` already uses for side-navigation — registered twice
into `kopling-core::community.topbar` via `Extension::ux()`: a `btn-ghost` "Log in" link and a
`btn-primary` "Register" link, `.after('login-link')` so ordering is stable. Both use
`Item`'s underlying pattern rather than reusing `Item` itself: `Item`'s view is `<li>`-wrapped
for a `<ul>` sidebar list (`k-core/views/portal/navigation/item.blade.php`), which isn't valid
markup inside the topbar's flex row, so `AuthLink` ships its own `<a class="btn ...">` view
instead.

**Guest-only visibility:** both entries use `Ux::when()` with a closure, `fn (?Person $person):
bool => $person === null`, not a permission id — this isn't a permission check, just "is anyone
logged in," and `SlotResolver::passes()` already supports a closure receiving `Auth::user()`
directly for exactly this case. Verified in `tinker`: `SlotResolver::resolve()` for the topbar
slot returns both links when `Auth::user()` is `null` and zero entries once a `Person` is set via
`Auth::setUser()`.

**Reused Core's own translations instead of adding new ones:** the link labels call
`__('kopling-core::auth.log_in')`/`__('kopling-core::auth.register')` directly rather than
duplicating "Log in"/"Register" strings into `auth-email-password`'s own `lang/en/messages.php`
— same English text already backs the `<h1>` on the pages these links go to, and the extension
already depends on Core's routes/events, so depending on Core's `auth` translation namespace too
isn't a new kind of coupling. Note this breaks from the sibling `Sidebar::defaults()`/`example`
precedent of passing a hardcoded literal English label as `Item` data (`'label' => 'Home feed'`)
— deliberately not followed here since a matching pre-existing translation was one call away.

**Status:** Decided & implemented. Verified via `Blade::render('<x-k::portal.slot
name="kopling-core::community.topbar" />')` in `tinker` (not a browser) that the rendered markup
is correct for a guest and empty once `Auth::setUser()` is called.

---

## 2026-07-11 — Split Community's chrome out of `layouts/community.blade.php` into its own `Chrome` component, so non-feed pages can sit inside it too

**Problem found:** `k-extensions/discussions/views/show.blade.php` (the `/m/{moment}` discussion
page) wrapped its content directly in `<x-k::portal.layout>` — the bare html/head/body shell
every portal layout is itself built on top of (see `Kopling\Core\Ux\Portal\Layout`'s own
docblock: region markup is each portal layout's job to add, this component owns none of it).
The topbar/sidebar/rail/composer chrome only existed inside `layouts/community.blade.php`, which
the discussion page never rendered through — confirmed by rendering `kopling-discussions::show`
in `tinker`: no `navbar` class, no "Home feed" sidebar link anywhere in the output. The
page's own docblock ("reuses the base portal shell... no coupling to core's feed") described
this as deliberate, but conflated two different things: not wanting the feed's
pagination-shaped `Context` (`getSubjectPaginator()`) is a real constraint; losing the chrome
entirely was an unintended side effect of the only escape hatch available at the time.

**Why discussions couldn't just render through `layouts/community.blade.php` as-is:** that
template hard-required a feed-shaped `Context` (`$context->getSubjectPaginator()`,
`$context->portal`) right in its own `@php` block, and `DiscussionController::show()` has no
Context at all — it renders a single `Moment`, not a paginated feed. There's also no Portal
bound to discussions' routes: `InjectPortal` middleware resolves the current `Portal` from the
*route name's* prefix (`Str::before($request->route()->getName(), '/')`), and
`discussions.show`/`discussions.reply` (`k-extensions/discussions/routes/web.php`) aren't
registered under the Community portal's own route group the way `community.php`'s routes are —
extensions' own routes load standalone, with no portal-scoping mechanism to opt into an existing
Portal's group (only `HasPortals` for declaring a *new* one).

**Fix:** extracted the topbar/sidebar/rail/composer markup out of
`layouts/community.blade.php` into a new component, `Kopling\Core\Ux\Community\Chrome`
(`k-core/src/Ux/Community/Chrome.php` + `k-core/views/community/chrome.blade.php`), exposing a
default slot (`{{ $slot }}`) for whatever main content the caller wants — the same `{{ $slot }}`
convention `Portal\Layout` itself already uses. `Chrome` resolves the Community portal itself via
`Manager::portals()->firstWhere('id', 'kopling-core::community')` in its own constructor, the
same self-sufficient-DI pattern `Sidebar` already uses for its own slot entries — so a caller
doesn't need a `Portal` instance on hand at all, sidestepping the missing-`InjectPortal`-binding
problem instead of trying to fix routing/portal-scoping for extension routes (a much bigger,
out-of-scope change). `layouts/community.blade.php` still owns everything feed-specific (tabs,
the htmx poller, the moment loop) and still needs its own `Context`-bound `$portal`/`$moments` —
it now just fills `<x-k::community.chrome>`'s slot instead of building the header/sidebar/rail
markup itself. `discussions/show.blade.php` now wraps its content in the same
`<x-k::community.chrome>` instead of the bare `<x-k::portal.layout>`.

**Verified in `tinker`, both pages, no regressions:** rendering `kopling-discussions::show` now
contains the navbar, the "Home feed" sidebar link, and the portal label "Community"; rendering
the feed page (`view($portal->layout)->with('context', $context)`, same call `IndexController`
makes) still contains the navbar, sidebar, tabs, the `#moments-feed` div, and the poll's
`hx-get` — confirming the split didn't change the feed page's own output, only where its chrome
markup physically lives.

**Status:** Decided & implemented. Not yet browser-verified by Daniël.

---

## 2026-07-12 — Extension load order: contract-dispatched rules, not a numeric priority

**Problem:** `Manager::extensions()` had no ordering control at all beyond `Core` always loading
first — every Composer-discovered extension loaded in whatever order `installed.json` happened
to list them. `k-extensions/admin`'s own docblock already flagged this as a real gap: other
extensions wanting to place settings/tools into Admin's Portal slots need Admin registered
first, and its TODO sketched the obvious next idea, a `composer.json`-declared
`extra.kopling.priority` int sorted by `Manifest`/`Manager`.

**Why a numeric priority was rejected:** a flat int scale is a single global namespace every
extension author — Kopling's own or a community author's, this is meant to support third-party
extensions the same way Flarum does — has to reason about collectively, and it degrades as more
extensions land between two existing values. It also can't express the actual relationship
("I need to come after Admin specifically"), only a rough position in a line every author has to
guess at.

**Decision:** two contracts in a new `Kopling\Core\Extension\LoadOrder` namespace
(`k-core/src/Extension/LoadOrder/`), resolved by a `Resolver` inside `Manager::extensions()`:

- `HasLoadOrder::loadAfter()`/`loadBefore()` — explicit, self-declared constraints, an array of
  Composer package names. The escape hatch for the rare extension with a genuine opinion about a
  specific other package.
- `InfluencesLoadOrder::loadOrderRules()` — `array<class-string, Directive>`, letting an
  extension place constraints on *other* extensions dispatched by capability contract rather
  than by package name. The extension that owns a contract (e.g. a future `HasSettings` Admin
  would own) declares `[HasSettings::class => Directive::After]` once; `Resolver` matches it
  against whichever installed extensions — including ones that don't exist yet, Kopling's own or
  a community author's — happen to implement that contract, via `instanceof`. Neither side ever
  needs to know the other's package name. This is the piece that actually solves Admin's problem
  without hardcoding "Admin loads first" anywhere in `Manager` — nothing about being "the admin
  panel" gets special-cased, matching the extension's own stated design goal.
- Explicit `HasLoadOrder` always wins over an inferred `InfluencesLoadOrder` rule for the same
  pair (`Resolver::edges()`), so a `HasSettings` implementor can still opt out and load before
  Admin if it genuinely needs to.
- `Resolver::sort()` is Kahn's algorithm; extensions with no relation to each other at all fall
  back to alphabetical-by-package order (deterministic, replacing `installed.json`'s
  unspecified order) rather than "whatever Composer happened to list." A genuine cycle throws
  `LogicException` naming the packages involved, same "throw on an extension author's own
  mistake" convention `Manager::themes()` already uses for an unrecognized theme token.
- New classes grouped under `Extension/LoadOrder/` rather than dropped into the existing flat
  `Extension/Contract/` — this feature is a related cluster (two contracts, an enum, a resolver),
  not a single marker interface, so it gets its own subtree the same way `Ux/` and `Extend/` do.

**Alternatives considered:** WordPress-style numeric priority (including a "named tiers" variant
mapping symbolic constants to ints under the hood) — rejected for the reasons above, a named
tier is still a flat global scale underneath. A single `dependencies(): array` ("load after
these") instead of two directions — rejected: can't express Admin's own stated rare case, an
extension needing to load *before* a package that never declared anything about it, without that
package's cooperation. Admin itself enumerating dependent extensions by package name — rejected
first in conversation, before this decision: admin can't know what community extensions will
exist, the same reasoning Flarum's own extension ecosystem has to solve for.

**Not built as part of this:** `HasSettings` itself, or any settings-registration mechanism on
Admin — this decision only lands the general load-order mechanism. Admin's own docblock TODO
now points at implementing `InfluencesLoadOrder` once `HasSettings` (or an equivalent contract)
actually exists.

**Status:** Decided & implemented (`HasLoadOrder`, `InfluencesLoadOrder`, `Directive`,
`Resolver`, wired into `Manager::extensions()`). Not yet covered by an automated test; not yet
verified beyond reading the code.

---

## 2026-07-12 — Routes (and now css/js) attach to a Portal via `ExtendsPortals`, not a directory convention

**Decision:** Routes, css, and js no longer register through `Manager::conventions()`'s bare
"the directory exists" rule. A new contract, `Extension\Contract\ExtendsPortals::extendsPortals():
array<Portal\PortalExtension>`, is now the only way anything attaches to a Portal's route group —
including for the extension that declared the Portal in the first place. `PortalExtension`
targets a Portal by its fully-qualified id (`'kopling-core::community'`, written out by the
author, same convention as `Ux::after()`/`Ux::before()`'s foreign references — `Manager` never
prefixes it), and offers `->routes()`/`->css()`/`->js()`, each validating the given path with
`file_exists()` the same way `Portal::routes()` used to.

**Why now:** Two real, already-documented problems, not a hypothetical: (1) `discussions`'
routes lived entirely outside any Portal (loaded via the directory convention), so `InjectPortal`
never resolved a Portal for its request and `Ux\Community\Chrome` had to hardcode
`firstWhere('id', 'kopling-core::community')` as a workaround, documented in its own docblock;
(2) `kopling/admin`'s Portal never called `->routes()`, so it silently registered zero routes —
`Arr::wrap(null) === []` swallows the `Route::group()` call with no error, no warning. Grouping
every route under an explicit Portal target, with one mechanism for owner and non-owner alike,
makes both classes of bug structurally harder to reintroduce: a route without a declared target
Portal doesn't get registered at all, and a Portal with nothing attached is exactly as visible
(`kopling:extensions:registrations` now reports "Portal attachments (ExtendsPortals)" per
extension) as one that does.

**What moved:**
- `Portal` (`k-core/src/Portal/Portal.php`) drops `$routes`/`routes()`/the already-dead
  `$middleware` constructor property entirely — it's identity only (id/label/path/layout/
  permission) again, matching its own docblock's stated intent.
- The portal route loop (`k-core/routes/web.php`) still computes `web` + optional
  `can:{permission}` middleware once per Portal, but now `require`s every attached extension's
  routes file inside that same `Route::group()`, via `Manager::portalExtensions()->get($portal
  ->id)` — order across extensions targeting the same Portal falls out of the already-solved
  `LoadOrder\Resolver` ordering, nothing new needed there.
- `Core::portals()` now only declares Community's identity; `Core::extendsPortals()` attaches
  its routes. `kopling-discussions`/`kopling-example` both migrated the same way, and both routes
  files dropped their now-redundant `Route::middleware('web')->group()` self-wrap (`loadRoutesFrom
  ()`'s own reason for needing it doesn't apply once the file is `require`d inside the Portal's
  own group). Route names shifted accordingly: `discussions.show` → `kopling-core::community/
  discussions.show`, `example.hello` → `kopling-core::community/example.hello` — every `route()`
  call and the one `Ux::add()` reference to `example.hello` updated to match. Verified end-to-end
  via `php artisan route:list`.

**css/js, previously unwired, landed in the same pass:** `Manager::conventions()` used to expose
`css`/`js` directory paths that nothing ever consumed (`ServiceProvider::boot()`'s own comment
said so directly) — pending a "head-assets outlet," not built. That outlet is now
`views/layouts/partials/head.blade.php`, reading `$portal` (see below) and looping
`Manager::portalExtensions()->get($portal->id)` for each extension's `css`/`js`.

**How a browser actually fetches a package-directory file — the part that made this "not too
hard" only after solving safely:** these files live inside `k-extensions/*`/`vendor/*`, not
`public/`, so nothing made them web-reachable. Rather than a route parameter shaped like
`{package}/{path}` (a path-traversal hazard: user input would be concatenated toward a filesystem
path), `Manager::extensionAssets()` builds a flat registry keyed by `hash('xxh3', $absolutePath)`
of every already-`file_exists()`-validated css/js path declared through `PortalExtension`.
`Http\Controllers\ExtensionAssetController` (route: `GET /_kopling/assets/{key}`, named
`kopling-core::assets`, registered outside any Portal group in the new `k-core/routes/assets.php`)
only ever looks `{key}` up against that map — a request can resolve to one of those specific
known-safe paths or nothing at all, never an arbitrary read. `Manager::assetUrl(?string $path):
?string` is the one place the same hash gets computed for both the registry and the `<link>`/
`<script>` tag, so they can't drift apart. Verified end-to-end: rendered `head.blade.php` in
tinker with Community's Portal bound and confirmed the emitted `<link>`/`<script>` tags for
`kopling-example`'s `css/app.css`/`js/app.js` point at `/_kopling/assets/{key}` with the right
`Content-Type`.

**`InjectPortal` now shares `$portal` as a view global (`View::share`), not just a request
attribute:** needed so `head.blade.php` (included inside `Ux\Portal\Layout`'s own view, which the
top-level Portal-rendering controller never threads `$portal` into — only `IndexController`'s own
`$context` carries it) can read the resolved Portal without new prop-plumbing through `Portal
\Layout`. Doesn't change `Chrome.php`'s own reasoning for hardcoding Community's lookup instead
of relying on injection — that's about rendering Community's chrome regardless of which Portal
(if any) the current route actually resolves to, an intentionally different question from "what
Portal is this request under."

**Alternatives considered:** Keeping `Portal::routes()` as a shortcut for the declaring extension
and `ExtendsPortals` only for attaching to *someone else's* Portal — rejected, two ways to do the
same thing, and it would have left the Admin-registers-nothing failure mode's root cause (an
easy-to-forget standalone call) in place for the one case (self-declared routes) it didn't cover.
Publishing/symlinking css/js into `public/` at install time — rejected: no Node/build step is
supposed to run on a Kopling host, and a publish command is one more thing an extension author has
to remember to run, the same asymmetry the load-order and route-middleware problems already came
from.

**Not built as part of this:** per-`PortalExtension` middleware beyond what the target Portal
itself already applies (`discussions` still authorizes inside its own controller, as before) —
deferred until a real case needs it, most likely once a Moderation Portal exists.

**Status:** Decided & implemented (`ExtendsPortals`, `PortalExtension`, `Manager::
portalExtensions()`/`extensionAssets()`/`assetUrl()`, `ExtensionAssetController`, `routes/
assets.php`, the rewritten portal loop in `routes/web.php`, `Portal`'s trimmed constructor,
`InjectPortal`'s `View::share`, and `Core`/`kopling-discussions`/`kopling-example` migrated).
Verified via `php artisan route:list`, `php artisan kopling:extensions:registrations`, and a
tinker-rendered `head.blade.php`. Not yet covered by an automated test.

## 2026-07-13 — Navigation split out of Sidebar into its own slot; nav-item rendering (menu vs. mobile dock) decided at the render call site, not at registration

The Community sidebar and right rail both ate too much horizontal width on mobile with no
responsive handling at all (`aside` was a fixed `w-64`, no breakpoint classes). A drawer wasn't
the right fit for the primary nav specifically -- Daniël wanted mobile nav links to land in a
bottom bar (daisyUI's `dock`, the renamed `btm-nav` in daisyUI 5) instead, which raised a real
modeling question: `Sidebar` (`kopling-core::community.sidebar`) mixed two unrelated things in
one slot -- `Sidebar::defaults()`'s own "Home feed" nav link, and `kopling-widgets`' `pulse`/
`tags` widgets (registered into the same slot, per that extension's own comment: "widgets on the
left, not the right rail"). Those widgets render `<div class="card">` blocks, so they were
already sitting inside `sidebar.blade.php`'s `<ul class="menu">` as invalid HTML (`<div>` isn't a
valid `<ul>` child) -- a real bug independent of mobile, just never surfaced. A dock needs a slot
guaranteed to hold nothing but nav-shaped entries, which this conflated slot couldn't provide.

**Registration itself needed no changes.** `Ux::add()`/`UxEntry`/`SlotResolver` are already fully
generic -- slot name + Blade component tag + an opaque `data` array, no coupling to navigation or
any other concept. The fix is entirely at the two layers above that:

- **New `Kopling\Core\Ux\Community\Navigation` component**, owning a new
  `kopling-core::community.navigation` slot -- nav links only. `Sidebar::defaults()`'s Home feed
  registration moved here; `Sidebar` itself keeps `kopling-core::community.sidebar` but drops its
  `defaults()` entirely (nothing in Core registers into it now, only extensions like
  `kopling-widgets` do) and `sidebar.blade.php`'s wrapper changed from `<ul class="menu p-4">` to
  a plain `<div class="p-4">`, fixing the invalid-HTML nesting as a side effect.
- **`Item` (`k-core/src/Ux/Portal/Navigation/Item.php`) gained a `$variant` constructor prop**
  (`'menu'` default, or `'dock'`), switching which half of `item.blade.php` it renders -- the
  existing `<li><a>` for `menu`, a new flat `<a>` (not daisyUI's example `<button>`; this is a
  real link, needs working right-click/open-in-new-tab, no JS required) with `dock-label` for
  `dock`. `$variant` deliberately isn't part of `UxEntry::$data` -- `$data` is static,
  author-declared config the registering extension controls (per `UxEntry`'s own docblock), but
  which markup an entry renders as is a render-time layout decision the extension has no business
  making. Instead whoever resolves the slot passes `variant="dock"` as a plain extra attribute
  into `<x-dynamic-component :component :data>` -- Blade drops unrecognized attributes into a
  component's `$attributes` bag rather than erroring, so any other component registered into this
  slot that doesn't declare a `$variant` prop just ignores it harmlessly; this needed no change to
  `UxEntry`, `SlotResolver`, or `Manager` at all.
- **`community/chrome.blade.php` renders `<x-k::community.navigation>` twice** -- once inside the
  now `hidden md:block` sidebar `<aside>` (default `variant="menu"`), once as `variant="dock"` as
  its own top-level sibling, deliberately *outside* that `<aside>`: `display:none` on an ancestor
  hides descendants outright regardless of the dock's own `position:fixed`/`md:hidden`, so the two
  variants can't share one DOM location. Each call is an independent `SlotResolver::resolve()`,
  same cost pattern `Slot` already uses for topbar/rail/composer (no caching layer exists anywhere
  in `Manager::ux()`, so this isn't a new inefficiency). The right rail keeps its pre-existing
  `hidden xl:block` treatment (it was already fine) rather than getting its own mobile variant --
  its one real consumer, `kopling-widgets`, is supplementary content, same as the sidebar's
  widgets, not something mobile needs a replacement surface for.
- Added `pb-16 md:pb-0` to chrome's content wrapper so the fixed-position mobile dock doesn't
  cover the composer footer, and `viewport-fit=cover` to `head.blade.php`'s viewport meta --
  daisyUI's own docs call this out as required for the dock to sit correctly inside iOS's safe
  area.

**Alternatives considered:** A daisyUI `drawer` for both sidebar and rail on mobile -- explicitly
ruled out per Daniël's ask, an off-canvas drawer doesn't match a bottom-nav mobile pattern.
Passing a `variant` through `UxEntry::$data` instead of as a render-time attribute -- rejected,
would have made every registering extension respsonsible for knowing about layout variants that
don't exist yet when it registers, the same coupling the `$context`/`$data` split on `UxEntry`
already exists to avoid. Keeping widgets and nav in one slot and just filtering by component type
at render time -- rejected, reintroduces a "does the slot consumer need to know what shape its
entries are" coupling that a slot boundary is supposed to remove; a second slot is one line of
`->in()` difference and costs nothing.

**Not built as part of this:** active-route highlighting (`dock-active`/menu equivalent) --
`Item`'s `menu` variant never had it either, out of scope here. A mobile treatment for the right
rail -- deferred until something real needs to live there on mobile (today it's just
supplementary widget content, same as the sidebar's).

**Same-day follow-up:** the `$variant` prop was renamed to `$surface` on both `Item` and
`Navigation` -- Daniël flagged `variant` as reading like a per-entry choice ("this item picks
menu or dock"), when every entry always renders into both surfaces unconditionally; nothing is
ever selected out, so the name shouldn't imply picking one. `as` was considered and rejected --
it collides with `Ux::add()->as()` (an entry's stable id), an unrelated concept, right next to it
in the same file. Also added a `Navigation::HOME_ICON` (inline `<svg>`, same style as every other
icon in this codebase -- theme-switcher, card controls, reply-dock, thread-title all use inline
SVG, none use an icon-font class) to the Home entry, rendered via `{!! $icon !!}` in both
surfaces. And made the mobile dock scrollable rather than letting daisyUI's default even-
distribution (`flex-basis:100%`, shrinking children) squeeze icons unreadably thin as more nav
entries are added: `overflow-x-auto justify-start [&>*]:shrink-0 [&>*]:basis-auto` on the `.dock`
wrapper, overriding daisyUI's own layered CSS the way daisyUI is designed to be overridden.

**Status:** Decided & implemented (`Navigation`, `Item::$surface`, `Navigation::HOME_ICON`,
`sidebar.blade.php`/`navigation.blade.php`/`item.blade.php`, `chrome.blade.php`,
`head.blade.php`'s viewport meta, `Core::ux()` swapped to `Navigation::defaults()`). Not yet
browser-verified by Daniël.

## 2026-07-14 — `Extend\Model::linksTo()`: a Moment card's detail-page link is a declared, native cascade, not a template override

`discussions` wants Moment cards to link out to its own discussion-thread page. The naive fix
(the extension overriding/replacing Core's `kopling-core::content` slot entry to add the `<a>`
itself) was rejected up front: Daniël wants this baked into Core as first-class functionality,
not an override — Core's own default title rendering should just know how to link out when an
extension has declared that it should, the same way `Content` already knows how to read
`title`/`body` off `$context->getSubject()` without any extension having to override it.

**The mechanism mirrors `Relation::eagerLoad()` exactly, deliberately.** That's the one existing
precedent in this codebase for "an extension declares something against a model at
registration time, and it cascades into render-time behaviour, conditionally, per request":
`Relation::eagerLoad(bool|callable $when)` stamps onto each relation definition
(`Extend\Model::relation()`), `Manager::models()` keeps the full `Extend\Model` collection
un-flattened, and `Ux\Context::getSubjectQuery()` filters/evaluates `$when` per-request
(`$portal`, `$request`, `$actor`) at query time. `Extend\Model::linksTo(string $route, array|
callable $parameters = [], bool|callable $when = true)` reuses the identical `$when` contract and
storage shape (`Model::$link`, a plain array, same as `$casts`) instead of inventing a second
"conditional declaration" pattern — one shape to learn for both relation eager-loading and card
linking.

**Resolution lives on `Ux\Context`, not `Manager`,** as `getSubjectUrl(): ?string` — looks up
`Manager::models()` for an `Extend\Model` targeting the subject's `getMorphClass()`, evaluates
`$when`, resolves `$parameters` (defaults to `[$subject->getKey()]`, or a `callable(Model):
array` for routes needing more), and calls `route()`. `Manager::models()` itself needed **no
new storage or side-effecting wiring step** — `$link` just rides along on the same
`Extend\Model` collection instance `Manager` was already caching; nothing had to be
flattened/merged the way `relations`/`casts` are, since only `Context` ever reads it, at
render time, one model at a time.

**Collision rule:** if two extensions both declare `linksTo()` for the same model class,
`getSubjectUrl()` takes the last-registered one — the same last-declared-wins rule
`Manager::models()`'s docblock already documents for colliding cast keys on the same model, kept
consistent rather than inventing a first-wins or error-on-conflict rule for this one case.
Untested in practice with a real second extension; only proven by fixture in
`ContextGetSubjectUrlTest`.

**`Ux\Card\Content`** (`kopling-core::card.content`, the `<h2 class="card-title">`) now passes
`$context->getSubjectUrl()` into its view and wraps the title in `<a>` only when non-null —
this is the "native, not override" part: `discussions` adds exactly one line
(`->linksTo('kopling-core::community/discussions.show')`) to its existing
`Model(Moment::class)` declaration in `Extension.php`, touches no Blade template, and the card
picks it up automatically.

**Collapsed a pre-existing duplication as a side effect:** the discussion-page route name was
already hardcoded independently in three places — `discussions/teaser.blade.php`,
`discussions/engage.blade.php`, and an unrelated extension, `thread-title/sticky.blade.php`.
All three now call `$context->getSubjectUrl()` (`sticky.blade.php` didn't have a `$context` in
scope at all — it reads `request()->route('moment')` directly — so it now builds one inline:
`new Context(subject: $moment)`) instead of naming the route themselves; the route name now
lives in exactly one place, `discussions/src/Extension.php`'s `linksTo()` call.

**Alternatives considered:** A Blade-override-based approach (extension replaces/edits the
`card.body` slot's `Content` entry) — rejected per Daniël's explicit ask for native, not
override, behaviour. A generic "extension-declared URL resolver" registered independently of
`Extend\Model` (e.g. on `Extend\Ux`) — rejected, `Extend\Model` is already documented as *the*
single place "which model" gets declared (casts + relations); a link is one more fact about a
model, not a Ux-slot concern.

**Testing note (unrelated gotcha surfaced along the way):** `Route::get(...)->name(...)`
registered ad hoc inside a Pest test body does not become resolvable via `route()`/
`Router::has()` without an explicit `app('router')->getRoutes()->refreshNameLookups()`
afterwards — `Route::name()` only mutates the `Route` instance's own action array;
`RouteCollection`'s name-lookup table is normally rebuilt once, by
`RouteServiceProvider::loadRoutes()`, after an entire routes *file* finishes loading, which
runtime ad hoc registration bypasses. `ContextGetSubjectUrlTest`'s `beforeEach()` calls
`refreshNameLookups()` explicitly; worth remembering for any future test that registers routes
inline rather than via a routes file.

**Status:** Decided & implemented (`Extend\Model::$link`/`linksTo()`, `Ux\Context::
getSubjectUrl()`, `Ux\Card\Content`/`card/content.blade.php`, `discussions/src/Extension.php`,
`discussions/teaser.blade.php`, `discussions/engage.blade.php`,
`thread-title/sticky.blade.php`, `ContextGetSubjectUrlTest` + `ModelLinker`/
`ModelLinkerConditional`/`ModelLinkerOverride` fixtures). Not yet browser-verified by Daniël. A
"make the whole card clickable, not just the title" follow-up (daisyUI's `card` has no native
whole-card-clickable modifier; would need the stretched-link pattern — an absolutely positioned
`<a>` under the card's interactive children via `z-index`) was discussed and deliberately not
built as part of this — only the title link was asked for.

---

## 2026-07-14 — Card `Control` becomes a real, slot-driven dropdown menu; new generic `Ux\Dropdown` primitive

**Problem:** `Ux\Card\Control` (the card's "⋮" button) was purely presentational — no slot, no
menu, "just the button a real menu will eventually hang off of" per its own docblock. The Pin
extension (kicked off this session, see roadmap.md) needed somewhere real to put a "Pin" action.

**Decision:** `Control` now owns its own slot, `Control::SLOT` ("kopling-core::card.control"),
resolved via `SlotResolver` exactly like `Top`/`Footer` already do — an extension targets it
with the same `Ux::add()`/`replace()`/`remove()`/`after()`/`before()`/`when()` calls it already
knows from `kopling-core::side-navigation`. Wired into `Core::ux()` alongside `Top`/`Footer`'s
own `defaults()` calls (empty, same reasoning `Footer::defaults()` already documents — no fake
actions, a real one registers when it exists).

A new generic primitive, `Kopling\Core\Ux\Dropdown` (`<x-k::dropdown>`, `k-core/src/Ux/
Dropdown.php` + `views/ux/dropdown.blade.php`), supplies the actual trigger/menu markup —
deliberately decoupled from `SlotResolver`/`UxEntry` (unlike `Control` itself): it takes a
`trigger` named slot and a default slot for `<li>` content, so it's reusable by anything that
wants a dropdown, slot-driven or not, rather than baking a second one-off Alpine/daisyUI
implementation the way `k-extensions/reactions`' modal had to. `Control`'s own view wraps its
resolved entries inside `<x-k::dropdown>`.

Built on the HTML Popover API + CSS anchor positioning (daisyUI's own recommended dropdown
syntax) rather than the older CSS-focus fallback — no JS, but no support in older Safari/
Firefox either, where the menu simply won't open. Flagged in roadmap.md as a watch item, not
resolved here.

**Alternatives considered:** A standalone button next to `Control` for Pin specifically (no core
change, matches how `reactions` adds its own footer entries) — explicitly rejected by Daniël:
"we build to get to the right state of the software, doing a temporary workaround is never good
enough" (see also the `feedback-build-right-not-workarounds` memory this prompted). Folding the
menu's own markup directly into `Control` rather than a separate `Dropdown` primitive — rejected
so the trigger/menu markup is reusable outside the card-control use case too.

**Status:** Decided & implemented (`Ux\Dropdown`, `views/ux/dropdown.blade.php`, `Ux\Card\
Control`, `views/card/control.blade.php`, `Core::ux()`, `CardControlTest` + `CardControlEntry`
fixture). Not yet browser-verified by Daniël.

---

## 2026-07-14 — Admin settings framework: `HasAdminSettings`, `Ux\Form\*`, flat `settings` key/value store

**Problem:** Drafting real functionality for extensions like `reactions` kept surfacing the same
gap: extensions need a way to expose admin-editable configuration, and there was no mechanism
for it at all — `kopling/admin`'s own Extension.php had carried a TODO since the load-order work
(2026-07-12) sketching exactly this, deliberately left unbuilt until a real need showed up.

**Decision:** A new `Extension\Contract\HasAdminSettings::adminSettings(): array<Ux\Form\Field>`
contract — deliberately not named `HasSettings`/`settings()`, so a future per-person preferences
contract can use that name without colliding (Daniël's explicit direction). `Field` (new value
object, `Ux\Form\Field`) declares *what* a setting is (`id`, `label`, `description`, `default`,
which `Ux\Form\*` component renders it) and nothing about persistence or placement — same split
`StorageRequest` already established for storage drives: the extension asks, Admin (the
extension that owns the concern) decides the backend. `$id` is prefixed by
`Manager::adminSettings()` (a new aggregator, grouped by owning extension id like
`storageDrivers()`) the same way `Permission`/`Portal`/`UxEntry` already are.

Three presentational `Ux\Form\*` components ship for this first pass — `Toggle`, `Input`,
`TextArea` (Select deliberately deferred until a real extension needs one). Rendered through the
existing `UxEntry`-style `ComponentTag`/`<x-dynamic-component>` pipeline, not a second bespoke
rendering path — `Field::$component` goes through `ComponentTag::resolve()` exactly like
`UxEntry::$component` does.

Persisted in a new flat `settings` table (`key` primary, `value` text) via `Kopling\Core\
Settings\Settings::get()`/`set()` — a plain `DB::table()` helper, not an Eloquent model, the
same choice `People\Group::hasPermission()`/`givePermissionTo()` already made for its own raw
`group_permission` pivot (no relation/cast to earn an Eloquent model's keep). Named `Settings`
(not `Setting`) specifically to avoid colliding with `Ux\Form\Field`-adjacent naming.

One page-level form, one Save button (Daniël's explicit choice over per-field htmx autosave,
weighing simplicity/no-race-conditions over reactions'-style responsiveness) —
`Admin\Controllers\SettingsController::store()` only ever writes fields the request actually
submitted.

`kopling/admin` finally implements `InfluencesLoadOrder` → `[HasAdminSettings::class =>
Directive::After]`, exactly what its own TODO called for, and gets its **first real
`ExtendsPortals` attachment** (`routes/web.php`, gated behind a new, more granular
`manage-settings` permission layered on top of the Portal's own `access-admin`) — until now
`kopling-admin::admin` was the one Portal in this codebase with zero routes attached, a shape
`RoutingTest.php`/`ManagerPortalTest.php` both explicitly asserted as "fine" (2026-07-12); both
updated to reflect the new reality instead.

**Surfaced gotcha (unrelated, worth remembering):** a Blade component property literally named
`$data` is never auto-exposed to its own view the way any other public property is —
`<x-dynamic-component>` reserves that name internally. Every existing card leaf
(`Avatar`/`Author`/etc.) already worked around this by unpacking `$data` into named variables
inside `render()`'s own `view(..., [...])` call rather than reading `$data[...]` straight from
the Blade template; `Toggle`/`Input`/`TextArea` initially missed this and were fixed to match.

**Also fixed in passing:** `reactions/views/components/rail.blade.php`'s own comment claiming
"extension CSS can't be linked onto the page yet (no head-assets outlet)" was stale — that outlet
was built and `reactions` already ships real CSS through it (`60c63f3`). Comment corrected;
roadmap.md's matching (incorrect) blocked-on entries removed.

**Alternatives considered:** Folding settings declaration into the existing `ChangesUx::ux()`
method (targeting a `kopling-admin::settings` slot) instead of a dedicated contract — rejected,
Daniël wanted a distinct, greppable capability (mirroring `permissions()`/`portals()`/`storage()`
each being their own contract) rather than everything funneling through the generic Ux
mechanism. Extensions returning already-instantiated `Ux\Form\*` component objects directly from
`adminSettings()` instead of declarative `Field`s — considered, rejected: would need a second,
non-`ComponentTag` rendering path, and `Field` still needs to exist anyway to attach `id`/
`default` to something.

**Status:** Decided & implemented (`Extension\Contract\HasAdminSettings`, `Ux\Form\Field`/
`Toggle`/`Input`/`TextArea` + views, `Settings\Settings`, `create_settings_table` migration,
`Manager::adminSettings()`, `kopling/admin`'s `Extension.php`/`routes/web.php`/
`Controllers\SettingsController`/`views/settings/index.blade.php`, `ListExtensionRegistrations`
updated to surface it too). Fields for real extensions (e.g. `reactions`) not yet declared — this
session only built the framework. Not yet browser-verified by Daniël.

---

## 2026-07-14 — Admin's chrome (sidebar + rail) scaffolded via the existing generic `Portal\Slot`; `Community\Navigation` deliberately not made Portal-aware

**Problem:** Admin's layout (`k-extensions/admin/views/layouts/admin.blade.php`) borrowed the
placeholder slot name `kopling-core::side-navigation` for its sidebar, and had no "rail" region
to mirror Community's. Daniël asked whether `Ux\Community\Navigation` — given how it already
implements the "Home" nav item — should become Portal-aware, so one class could serve both
Community and Admin.

Investigating surfaced a real, already-live bug, not a hypothetical one: `UxEntry`/
`SlotResolver`/`Manager::ux()` carry no concept of Portal scoping at all — a `UxEntry` is only
ever tagged with a slot-name *string*. `k-extensions/example`'s illustrative "Hello" nav item and
`kopling/admin`'s real "Settings" nav item both targeted the exact same slot string,
`kopling-core::side-navigation` — isolation between Community's and Admin's chrome existed only
because their layouts happened to render disjoint slot names, not because anything enforced it.

**Decision:** Two separate calls, both against genericizing:

1. **`Community\Navigation` stays exactly as it is — not made Portal-aware.** Every existing Ux
   region component (`Card\Top`/`Footer`/`Control`, `Community\Navigation`/`Sidebar`/
   `ThemeSwitcher`) is one class = one hardcoded `const SLOT` + a no-args static `defaults()`;
   there is no precedent anywhere in this codebase for a region parameterized at runtime by which
   Portal it belongs to. `Navigation`'s actual content is Community-specific top to bottom
   anyway (its dual `menu`/`dock` surface rendering exists specifically for Community's mobile
   bottom dock; `defaults()` hardcodes the Community "Home" route/icon) — none of it
   generalizes to Admin.
2. **No new `Admin\Navigation`/`Admin\Sidebar`/`Admin\Chrome` classes either.** `Community\Chrome`
   exists because Community's chrome wraps content from multiple route entry points (feed,
   discussions' show page, ...) outside the `Portal::layout` mechanism. Admin has exactly one
   entry point today and it already *is* `Portal::layout` via classic `@extends`/`@yield` —
   building a parallel `Chrome` class now would mean converting Admin's layout mechanism for no
   current benefit.

Instead: Admin's layout now uses the already-fully-generic `Kopling\Core\Ux\Portal\Slot`
directly for both regions, under its own portal-owned slot names — `kopling-admin::admin.
navigation` (renamed from the placeholder `kopling-core::side-navigation`) and a new
`kopling-admin::admin.rail` (starts empty, same as Community's rail before `widgets` existed —
somewhere for a future admin-facing widget extension to land). `Community\Chrome` itself already
uses this same primitive, unclassed, for its own rail (`kopling-core::community.rail`) — no
dedicated `Rail` class exists there either, so this isn't a new pattern, just applying an
existing one. `example`'s illustrative registration was retargeted to
`kopling-core::community.navigation`, the slot it actually belongs to (its route and
`extendsPortals()` target are both Community's) — it was never correctly Admin's to begin with.

**Deferred, not built:** real structural Portal-scoping (a `UxEntry` actually carrying/validating
a Portal id) would touch the `Ux` fluent builder every extension's `ux()` method uses — a
materially larger change than this leak, which had exactly one forcing example, needed. Logged
in roadmap.md ("Ux / extensibility") for whenever a second forcing example shows up.

**Alternatives considered:** Making `Navigation`'s slot name a computed string
(`"{$portal->id}::navigation"`) instead of a literal constant — rejected, breaks the "an
extension can import/reference a stable `SLOT` constant" property every other region relies on,
and only "fixes" the exact one collision already fixed more cheaply by picking distinct names.

**Status:** Decided & implemented (`k-extensions/admin/views/layouts/admin.blade.php`,
`k-extensions/admin/src/Extension.php`, `k-extensions/example/src/Extension.php`, docblock
accuracy pass on `SlotResolver`/`UxEntry`/`Card\Top`/`Card\Control` swapping the now-dead
`kopling-core::side-navigation` example for the still-real `kopling-core::community.navigation`).
Not yet browser-verified by Daniël.

---

## 2026-07-14 — `Authorization\Permission` split: the declarative value object moved to `Extend`, the name freed for a real Eloquent model over `group_permission`

**Problem:** `Kopling\Core\People\Group::hasPermission()`/`givePermissionTo()`/
`revokePermissionTo()` ran raw `DB::table('group_permission')` queries by hand, the only place
in `People\*` still doing that (`Person::hasPermission()` also does, but wasn't in scope here —
its query joins `group_permission` *and* `group_person`, a different shape). Meanwhile
`Kopling\Core\Authorization\Permission` already existed, but as the *declarative* value object
(`id`/`label`/`description`/`default`/`callback`) an extension's `HasPermissions::permissions()`
returns — not a model, and not what its own namespace ("Authorization") suggested it might be.

**Decision:** Two moves, not one:

1. The existing declarative value object moved to `Kopling\Core\Extend\Permission` — the same
   namespace `Extend\Model`/`Extend\Ux` already occupy for "what an extension declares," which is
   exactly what this class is and always was. No behavior change, pure rename; every `use
   Kopling\Core\Authorization\Permission;` (`Core.php`, `Manager.php`, `HasPermissions.php`,
   `admin`/`discussions`/`example`'s `Extension.php`, `PermissionDeclarer` test fixture) and two
   prose docblock mentions (`Portal.php`, `Person.php`) updated to match.
2. `Kopling\Core\Authorization\Permission` now names something new: a real Eloquent model over
   `group_permission` — one row = one Group's grant of one permission id. `Group` gets a real
   `permissions(): HasMany` relation; `hasPermission()`/`givePermissionTo()`/
   `revokePermissionTo()` now read/write through it instead of `DB::table()`.

`group_permission` has a composite primary key (`group_id`, `permission`), no auto-incrementing
`id` — handled by `public $incrementing = false` on the model. Never an issue in practice: a
grant is only ever queried/created/deleted by its actual columns through the relation, never
fetched or saved by a single id.

**What this model is *not*:** a catalog of every permission that exists. A permission's own
definition (label/description/default/callback) still lives entirely in code, computed fresh on
every request by `Manager::permissions()` — the migration's own comment on this already explains
why no separate `permissions` table exists, and that remains true. `Authorization\Permission`
only replaces the raw-SQL grant-row queries; it never gained a `label`/`description` of its own.

**Trade-off accepted:** `givePermissionTo()` changed from `DB::table(...)->insertOrIgnore(...)`
(atomic, DB-level conflict-ignore) to `$this->permissions()->firstOrCreate([...])`
(check-then-create, a narrow race window under concurrent identical grants). Accepted as a minor
theoretical regression — permission grants are low-frequency, typically admin-initiated writes,
not a hot concurrent path — in exchange for a real Eloquent relation instead of hand-rolled SQL.

**Status:** Decided & implemented (`Extend\Permission`, `Authorization\Permission`,
`People\Group::permissions()`/`hasPermission()`/`givePermissionTo()`/`revokePermissionTo()`, all
`use` updates listed above). `Person::hasPermission()`'s own raw `DB::table()` join across both
pivots was explicitly left alone — out of scope for this pass, noted here in case it's picked up
later. Verified: full test suite (52 passing, including `GateWiringTest`'s existing
grant/check coverage) and a live run of `kopling:demo:seed-admin` against the real dev DB.

---

## 2026-07-15 — Dropped closures from UxEntry/Permission; added `Guest`; flatfile cache for Manager's aggregations

**Closures gone:** `UxEntry::$condition` and `Extend\Permission::$callback` no longer accept a
`\Closure` — permission ids (strings) only, so entries stay plain, cacheable data. Broke 2 real
usages (composer's "signed-in only", auth-email-password's guest-only login/register links) —
composer's moved into its own view (`@auth`); auth-links now use a new `kopling-core::guest`
permission.

**`Guest extends Person`** (never persisted, `hasPermission()` hard-`false`) substitutes for
`null` in the Gate closure. `Permission::$allowsGuests` grants a permission to Guest specifically,
independent of `$default` (which is unchanged — still means "everyone," guest included).

**New flatfile cache** (`RegistrationCache`, `bootstrap/cache/kopling-registrations.php`,
separate from `Manifest`'s `kopling-extensions.php`) for `Manager::permissions()/portals()/
portalExtensions()/storageDrivers()/ux()/themes()/adminSettings()/commands()` — all deterministic
given the extension set, all now check the cache before computing live. Each value object
(`Permission`, `Portal`, `PortalExtension`, `StorageRequest`, `UxEntry`, `Ux\Form\Field`) got
`toArray()`/`fromArray()`. Explicit-only for now (`kopling:extensions:cache`), no automatic
trigger — editing an extension's `ux()` isn't a Composer operation, so an automatic hook (unlike
`Manifest`'s) would silently go stale during dev. `models()` stays uncached (side-effecting, not
a pure aggregation). Verified: full suite passing both with and without the cache file present,
plus a manual round-trip (build → Gate checks → routes → registrations command all correct
reading from cache).

**Regression fix, same day:** `Manager::applyUxAdd()` always re-prefixed `$entry->condition`,
which broke `auth-email-password`'s new `->when('kopling-core::guest')` (became
`kopling-auth-email-password::kopling-core::guest`, denied login/register links entirely). Fixed
to skip prefixing when `$condition` already contains `::` — same "already fully-qualified"
convention `$after`/`$before`/`PortalExtension::$portal` use. Covered by a new test
(`ManagerUxTest`, `UxAdder` fixture's `foreign` entry).

**Second regression, same day:** `kopling:demo:seed-admin`'s "grant every permission" loop also
granted `kopling-core::guest` to the admin's real Group, so the admin was seen as a guest too
(login/register links kept showing). Fixed at both ends: `allowsGuests` permissions now made
exclusive in the Gate closure (a real Person's Group grant is ignored for them, not just unlikely
to happen), and the seed script skips granting them at all.

---

## 2026-07-15 — `Extend\Model::creating()`/`saving()`: model lifecycle hooks for extensions

Added two nullable-`Closure` properties (`creating`, `saving`) to `Extend\Model`, applied by
`Manager::models()` via the target model's own native Eloquent `Model::creating()`/`saving()` —
lets an extension inject a column value at creation (e.g. stamping an `ip` onto `Reply`) or
transform/sanitize an attribute on every save (e.g. expanding template hooks in a posted body),
without the target extension's model knowing about it.

Reused the existing `ExtendsModels`/`Extend\Model` contract rather than adding a new one — same
"declare something about a target model, `Manager` wires it up" shape already used for
relations/casts. Chosen over the casts mechanism's own approach (`Database\Model::$extendedCasts`,
a static registry that only takes effect if the model extends `Kopling\Core\Database\Model` —
which no shipped model actually does) because Eloquent's `creating`/`saving` statics work on any
Eloquent model with zero base-class opt-in, the same reach `resolveRelationUsing()` already has.

Single nullable slot per declaration (`?Closure`, matching `$link`'s shape), not an accumulating
array — avoids forcing a declaration that only needs one of the two hooks to pass an unused
empty value for the other, a boilerplate pattern already visible in `HasLoadOrder::loadAfter()`/
`loadBefore()`. Multiple extensions targeting the same model each get their own declaration and
their own hook, and both fire in load order — Eloquent supports multiple listeners per event
natively, so there's no collision rule to write, unlike relation-name/cast-key clashes.

**Status:** Decided & implemented (`k-core/src/Extend/Model.php`, `Manager::models()`,
`tests/Fixtures/Extensions/ModelHooker/*`, `tests/Feature/Extension/ModelHookingTest.php`,
`extending-patterns.md` Section 3). Verified: full test suite.

---

## 2026-07-15 — `HasLoadOrder` split into `LoadsAfter`/`LoadsBefore`

Supersedes the `HasLoadOrder` half of the 2026-07-12 "Extension load order" decision above.
`HasLoadOrder::loadAfter()`/`loadBefore()` was the only contract in the codebase requiring two
methods, forcing an implementor who only cared about one direction to still declare a no-op for
the other (`k-extensions/example/src/Extension.php` carried a dead `loadBefore(): array {
return []; }` for exactly this reason). Audited every other contract in `Extension/Contract/`
and `Extension/LoadOrder/` for the same problem — none had more than one method, so this was an
isolated case, not a pattern needing a broader fix.

Split into two single-method interfaces, `LoadsAfter`/`LoadsBefore`, each independently
opt-in — same "implement zero, one, or many" principle every other contract already follows,
just applied one level more granularly than usual. `Resolver::edges()` now checks each
`instanceof` separately instead of gating both loops behind one combined check.
`example/Extension.php`'s dead `loadBefore()` is gone; it now implements only `LoadsAfter`.

**Status:** Decided & implemented (`LoadsAfter`, `LoadsBefore`, `Resolver::edges()`,
`example/Extension.php`, `tests/Fixtures/Extensions/LoadOrder/{AfterOnlyExtension,
BeforeOnlyExtension}.php`, `LoadOrderResolverTest`, `extending-patterns.md` Section 11,
`kopling-landing/public/extend.html`). Verified: full test suite.

---

## 2026-07-15 — Icon extensibility: Blade Icons + a semantic `HasIcons`/`ChangesIcons` layer, Font Awesome as the baseline

Replaces 100% hand-authored inline `<svg>` markup (Item/Navigation/ThemeSwitcher/Card\Control/
reply-dock/thread-title) with `blade-ui-kit/blade-icons` (`owenvoke/blade-fontawesome` as the one
bundled pack — MIT code license, CC BY 4.0 icons, free tier only, no Pro/npm). Chosen over
letting extensions reference pack-prefixed component tags (`<x-fas-home/>`) directly: Blade
Icons has no "active pack" concept of its own — a component tag is permanently bound to one
installed set — so referencing packs directly would make swapping the site's icon pack mean
editing every extension by hand, repeating Flarum's own well-known icon-extensibility problem.

Mirrors the existing `Permission`/`ChangesTheme` shape rather than inventing a new one:
`HasIcons::icons()` declares a semantic id + a mandatory Font Awesome default (`Extend\Icon`,
prefixed by `Manager` the same as `Permission::$id`); `ChangesIcons::iconMap()` (an icon-pack
extension) maps already-declared ids to its own icon names, tolerantly — an unrecognized/
uninstalled id is left alone, same convention `Ux::after()`/`before()` already use, not validated
against a fixed enum the way `ChangesTheme` validates against `Theme\Token` (icon names are open
and extension-owned, unlike the small closed set of themeable CSS custom properties). Render-time
resolution (`Kopling\Core\Ux\Icon`, `<x-k::icon name="...">`) renders through the `svg()` helper,
never `<x-dynamic-component>` — confirmed against `BladeUI\Icons\Factory`'s own source that its
fallback chain only runs inside `svg()`/`@svg`, not through a pre-resolved component tag, which
would throw Laravel's own "component not found" error on a miss instead of degrading.

No admin picker UI yet (deliberately deferred — needs a `Select` `Ux\Form\*` field type, currently
only `Input`/`TextArea`/`Toggle` exist). `Icon` already reads the final, prefixed Settings key
(`kopling-core::icon-pack`) a future picker would write, so wiring that up needs no change here.
Every icon renders via its Font Awesome default until then.

**Status:** Decided & implemented (`Extend\Icon`, `Extension\Contract\{HasIcons,ChangesIcons}`,
`Manager::{icons,iconPackChoices,iconPackMappings}`, `Ux\Icon`, `views/ux/icon.blade.php`,
`Core::icons()`, `reply-dock`/`thread-title` extensions' own `icons()`, `CacheRegistrations`,
`ListExtensionRegistrations`, `ManagerIconTest`, `Feature/Ux/IconTest`). Verified: full test
suite (writing `ManagerIconTest` surfaced the pre-existing `Settings::get()` bug fixed below).

---

## 2026-07-15 — `Settings::get()` catches `\RuntimeException`, not just `\PDOException`

`Manager::extensions()`'s default (`includeDisabled: false`) path calls
`EnabledExtensions::isEnabled()` → `Settings::get()` → `DB::table()` on every call, including
from `fakeManager()`-based bare Unit tests that deliberately boot no Laravel app at all. There,
`DB::table()` fails before ever reaching a PDO call, with the facade base's own `\RuntimeException`
("A facade root has not been set") — not caught by the existing `\PDOException`-only guard, so
every such Unit test across `ManagerPermissionTest`/`ManagerPortalTest`/`ManagerStorageTest`/
`ManagerThemeTest`/the new `ManagerIconTest` failed (13 of the suite's failures, all one bug,
found while adding icon tests above).

`\PDOException` is already a `\RuntimeException`, so widening the catch to `\RuntimeException`
covers both cases with the one change, no new exception type invented. `EnabledExtensions::all()`
degrades to its own already-documented `null` ("nothing has ever been toggled" — everything
enabled), the correct behavior for a context with no settings table to read at all.

**Status:** Decided & implemented (`Settings::get()`). Verified: full test suite — fixed 16 of
17 failures the icon work's own tests had surfaced; one unrelated, pre-existing
`SettingsControllerTest` seeding failure remains, out of scope here.

**Update (2026-07-15):** that remaining `SettingsControllerTest` failure was
`personWithManageSettings()` being called twice in one test ("toggles a disabled extension back
to enabled on a second call"), creating two `Person` rows with the same email. Fixed by calling
it once and reusing the person across both requests.

## 2026-07-15 — `<x-k::modal>`: native `<dialog>`, not Popover API, for Pin's prerequisites

**Problem:** Pin (see roadmap.md) needs a modal for its reason/dates/groups form, and a new
People/Groups admin UI needs one for group assignment. No `<x-k::modal>` primitive existed —
every extension needing a modal hand-rolled its own Alpine one (`k-extensions/reactions`'s).

**Decision:** `Kopling\Core\Ux\Modal` (`k-core/src/Ux/Modal.php` + `views/ux/modal.blade.php`),
same shape as `Dropdown` (trigger slot + default slot, purely presentational, no opinion on
content) but built on the native `<dialog>` element instead of Dropdown's Popover-API approach:
a form-bearing modal needs real focus-trapping, which `showModal()` gives natively (inert
background, focus trap, Escape closes) and the Popover API deliberately does not provide.
`<dialog>` has no attribute-only opener though, so one generic delegated click listener was
added to `k-core/src/Ux/js/app.js` (`[data-modal-show]` → `showModal()`); closing needs zero JS
(a sibling `<form method="dialog" class="modal-backdrop">` and native Escape both close it).
`$id` slugs the label (`modal-manage-groups-a1b2`) plus a short random suffix, for readability
and multi-instance uniqueness.

**Alternatives considered:** daisyUI's older checkbox-trick modal — rejected, no native
semantics, strictly worse a11y, legacy-only.

**Status:** Decided & implemented (`Ux\Modal`, `views/ux/modal.blade.php`, `ModalTest`). Not yet
browser-verified by Daniël.

## 2026-07-15 — `Ux\Form\MultiSelect`: a checkbox-list picker, generic rather than Group-specific

**Problem:** Pin's Groups targeting and the new People/Groups admin UI (group assignment) both
need to pick from a list of Groups. No reusable multi-select/picker component existed in
`k-core/src/Ux/Form/`.

**Decision:** `Kopling\Core\Ux\Form\MultiSelect` — generic, not Group-specific, same
content-agnostic shape as `Toggle`/`Input`/`TextArea` (one `array $data` prop; doesn't know it's
picking Groups). Renders a checkbox per option inside a `fieldset` (matches `Toggle`'s markup
shape), submitting as `name="{name}[]"`. Values come through `collect(...)->map(...)->all()`,
not a raw `(array)` cast — `(array) $someCollection` reads the object's internal properties, not
its items, and silently corrupts the list (caught by `PeopleControllerTest`'s real-HTTP test,
not the component's own unit test, which passed plain arrays).

**Alternatives considered:** native `<select multiple>` — rejected, poor UX, no daisyUI styling.
A searchable combobox — rejected, no current caller needs search over a large option set.

**Status:** Decided & implemented (`Ux\Form\MultiSelect`, `views/ux/form/multi-select.blade.php`,
`MultiSelectTest`).

## 2026-07-15 — People/Groups admin UI: reuses the already-declared `manage-people` permission, no new migration

**Problem:** Pin's Groups targeting has nothing to target in practice until an operator can
assign a Person to a Group — the data model (`Person::groups()`, `group_person` pivot) already
supports it, but no UI existed anywhere (`k-extensions/admin` only had the Settings framework).

**Decision:** Two controllers in `k-extensions/admin` (`PeopleController`, `GroupsController`),
following `SettingsController`'s shape — plain forms, full-page POST+redirect, no htmx (a
low-frequency admin action, not `SettingsController::toggle()`'s reactive per-field case).
`Core::permissions()` already declared `manage-people`, unused anywhere until now — reused it
rather than inventing a new permission string. No new migration: `group_person` (composite PK,
cascading FKs, no extra columns) already supports add/remove with no history, matching Pin's own
"one active pin, no history" bias. Group assignment's UI is the new `<x-k::modal>` wrapping the
new `<x-k::form.multi-select>`.

**Also fixed in passing:** `access-community`'s description was a copy-paste of
`manage-people`'s ("Create, edit, and remove people and groups") — corrected to describe what
`access-community` actually gates.

**Status:** Decided & implemented (`PeopleController`, `GroupsController`,
`k-extensions/admin/routes/web.php`, `Extension::ux()` nav entries, `PeopleControllerTest`,
`GroupsControllerTest`). Person detail/profile page (own email/password, avatar) stays
out of scope. Not yet browser-verified by Daniël.

## 2026-07-16 — `QueryingMoments`/`RenderingCard`: Pin's feed-reorder and card-styling needs reuse the existing `ListensToEvents` mechanism, not a new contract

**Problem:** Pin needs pinned-and-visible moments to float into their own section above the
regular feed (excluded from it, so nothing shows twice) and to render with a reason-colored
border — but the feed query (`IndexController`, `LatestMomentsController`) had no extension
point at all, and `Card`'s own docblock stated its outer wrapper was "not itself
replaceable/extensible."

**Decision:** Daniël's own call during planning: reuse `ListensToEvents`/`Manager::listeners()`
(already real, tested infrastructure — the same pattern `auth-email-password` already uses for
`AttemptLogin`/`AttemptRegistration`) instead of inventing a parallel `Extend\Model` hook
mechanism. Two new, narrowly-scoped Core events, each with mutable public state a listener acts
on:
- `Content\Event\QueryingMoments` (carries the query `Builder`) — dispatched in
  `IndexController::__invoke()` and both `LatestMomentsController` methods, right before the
  query runs. A listener mutates `$event->query` directly (Eloquent's own query methods already
  mutate `$this` and return it).
- `Ux\Card\Event\RenderingCard` (carries the `Context` and an accumulating `$classes` array) —
  dispatched from `Card`'s own constructor. A listener calls `$event->addClass(...)`; `Card`
  joins the result into its wrapper's class list.

`Card`'s docblock was updated — its wrapper isn't slot-driven, but it's no longer sealed either.

**Status:** Decided & implemented. Full suite green (108 tests).

## 2026-07-16 — `Ux\Form\Select`: the single-value counterpart to `MultiSelect`

**Problem:** Pin's reason dropdown needs a single-value select; only `MultiSelect` (checkboxes)
existed. `Icon.php`'s own docblock had already anticipated this gap.

**Decision:** `Kopling\Core\Ux\Form\Select` — same `array $data` shape as `Toggle`/`MultiSelect`
(`name`/`label`/`description`/`options`/`value` falling back to `default`), a native `<select>`
inside the same `fieldset`/`legend` wrapper. No combobox/search — same reasoning `MultiSelect`
already documented (no current caller needs search over a large option set).

**Status:** Decided & implemented (`Ux\Form\Select`, `SelectTest`).

## 2026-07-16 — Pin extension: `k-extensions/pin`

**Problem:** the original Pin feature request — pin a Moment with a reason, a reason-mapped
color, an optional start/end window, and optional Groups targeting — never got built; the
session that started it built its prerequisites instead (`Dropdown`, `Modal`, `MultiSelect`, the
People/Groups admin UI — see their own entries above).

**Decision:** `kopling/pin`, structured after `kopling/reactions` (attaches to `Moment` via
`ExtendsModels`, no core changes to the model itself) and `kopling/admin` (controller/permission
shape). Key choices:
- `pins.moment_id` unique — one active pin per moment, re-pinning updates the same row via
  `updateOrCreate`, no history table.
- `group_pin` pivot (named to match Eloquent's own alphabetical-model pivot-naming convention —
  same reason `group_person` isn't `person_group`) — empty groups means visible to everyone,
  non-empty means visible only to a Person in at least one targeted Group.
- One new flat permission, `kopling-pin::pin-moments`, gating both the Control-menu entry and
  (re-checked server-side) the controller — no per-instance/ownership policy exists anywhere in
  this codebase to build on instead.
- `Pin::visibleFor()` is a plain PHP filter over all pins (eager-loaded), not one SQL join —
  pins are curated/rare by nature, so this stays simple; revisit only if that assumption breaks.
- The Control-menu entry is one component, not two: whether it shows "Pin" or "Edit pin" +
  "Unpin" is per-Moment state, not per-actor, so it can't be expressed via `Ux::add()->when()`'s
  permission gate — the component's own view decides, rendering both actions inside `Control`'s
  one forced `<li>` when a pin already exists.
- `DecoratePinnedCard`/`ControlEntry` read `$moment->pin` (the magic relation accessor), not
  `getRelation('pin')` — the main feed's paginator eager-loads it for every card in one batch,
  but a moment freshly prepended by `LatestMomentsController`'s live poll never goes through
  that path, so `getRelation()` would throw there; the accessor lazy-loads instead, at the cost
  of one extra query only for that rare, one-at-a-time case.
- Caught before shipping: `DecoratePinnedCard` builds its border/bg classes dynamically
  (`"border-{$pin->color()}"`) inside a plain `.php` listener, but Tailwind's `@source` only
  scans `.blade.php` files — a dynamic class string built in PHP would never reach the compiled
  CSS. Fixed with a literal safelist comment in `pinned-section.blade.php` (Pin's reason set is
  small and fixed by design, so this is simpler than restructuring the listener to build markup
  in Blade instead).

**Also fixed in passing (found via `PinControllerTest`'s guest test):** a plain (non-htmx) POST
to any `auth`-gated route while logged out crashed with `RouteNotFoundException` —
`Authenticate::redirectTo()` calls `route('login')` directly, and this app only ever defines a
namespaced login route. `Kopling\Core\Http\Exceptions\RedirectHtmxUnauthenticated` (renamed
`RedirectUnauthenticated`) only ever handled the htmx case; the crash happens at throw-time,
before any exception renderable gets a chance to run, so that class alone can't fix the
non-htmx case.

First attempt put the fix in `bootstrap/app.php`'s `$middleware->redirectGuestsTo(...)` —
reverted: this repo already decided (2026-07-09, "htmx auth-wall responses...", above) that
root-owned bootstrap/config files never carry application logic, precisely so Core keeps behaving
like an ordinary Laravel package rather than a fork of the skeleton. Should have checked that
entry before touching `bootstrap/app.php` at all.

Corrected fix: `Illuminate\Auth\Middleware\Authenticate::redirectUsing()` is the same static hook
`Middleware::redirectGuestsTo()` (a `bootstrap/app.php`-only API) calls internally — calling it
directly from `Kopling\Core\Provider\ServiceProvider::boot()` reaches the identical fix without
touching the skeleton. One real gotcha surfaced getting this right: Laravel's own
`ApplicationBuilder::withMiddleware()` registers its *default* `redirectGuestsTo(fn () =>
route('login'))` via `$this->app->afterResolving(HttpKernel::class, ...)` — i.e. it doesn't run at
`bootstrap/app.php`-evaluation time, but whenever `Kernel::class` is *first resolved*. `boot()`
already does `$this->app->make(Kernel::class)` a few lines down (to append `InjectPortal`) — that
call is what triggers Laravel's default, so `redirectUsing()` must be called *after* it, not
before, or the default clobbers it right back. Reactions never hit any of this because its own
forms are all htmx-driven; Pin's aren't.

**Status:** Decided & implemented (`k-extensions/pin`, `PinControllerTest`,
`FeedVisibilityTest`, `RedirectUnauthenticatedTest`). Full suite green (108 tests). `help ->
success` in `Pin::REASONS` was the agent's own pick, not yet confirmed by Daniël. Not yet
browser-verified.

## 2026-07-17 — TipTap 3.x rich-text editor (v1: a Notion-styled editor, not the paid template)

**Decision:** `Moment`/`Reply.body` is repurposed (no schema change) to hold canonical
ProseMirror JSON; a new `body_html` column holds sanitized HTML rendered server-side at write
time by `Kopling\Core\Ux\Editor\DocumentRenderer` — a hand-written tree-walker over a closed,
PHP-declared node/mark catalog (`Ux\Editor\EditorNode`), not an HTML sanitizer over
client-supplied markup. Extensibility is a new `ChangesEditor` contract (mirrors `ChangesTheme`'s
"vote into one closed, Core-owned catalog" shape, not `HasIcons`'s "declare your own namespaced
thing") — `Manager::editorNodes()` unions every installed extension's vote with Core's own
defaults. The swappable-editor-implementation slot (`Ux\Editor::SLOT`) reuses the existing
`ChangesUx`/`Ux::replace()` mechanism verbatim, same as `Card\Body`/`Top` — no new contract
needed just to make swapping possible.

**Scope correction:** `tiptap.dev/templates/notion-like-template` (what was originally asked
for) is **not** open-source — verified directly against the page: Tiptap Start plan ($59/mo+),
React-only, depends on Tiptap's paid "Tiptap UI Components" package plus Cloud
collaboration/AI. Built a Notion-*styled* editor instead (slash-command menu via the free
`@tiptap/suggestion` primitive, block-style layout) from free/MIT primitives only
(`@tiptap/core`+`starter-kit`+`extension-{link,underline,task-list,task-item,placeholder}`+
`suggestion`+`pm` — every package's license individually checked via `npm view <pkg> license`
before adding it to `package.json`).

**v1/v2 split:** v1 ships one Core-owned editor; extensions may only vote which of a closed
node/mark set is enabled (no new client-side behavior). A genuinely alternative editor
implementation or a bespoke custom node needs real per-extension JS bundling, which doesn't
exist yet (the "documented but unbuilt" per-extension `resources/`+`dist/`+release-workflow
CLAUDE.md already flags) — deferred as v2, not attempted here.

**Other notes:**
- `editor.js` (tiny, always-loaded shim) dynamically `import()`s `editor-tiptap.js` (the real
  ProseMirror payload, ~125KB gzipped) only once a page has a mount point — first dynamic
  `import()` in this codebase. Own Vite entry pair in both `vite.config.js` and
  `vite.core-dist.config.js`, not folded into `app.js`.
- Editor JS is plain vanilla + an imperative API on the mount element (`.kopEditor`), not
  `Alpine.data()` — same ordering gotcha `reply-dock` already worked around.
- `reply-dock`'s quote/canned-reply mechanism and discussions' `"> Author: text"` regex
  quote-parser were both real migration work, not cosmetic — quotes are now real, directly
  editable `blockquote` nodes inserted via the imperative API, not string-prefixed `FormData`.
  A backfill migration re-derives them for historical rows (`Discussions\Support\
  LegacyReplyDocument`, pulled out of the anonymous migration class specifically so it's
  testable).
- First `FormRequest`s in this codebase (`StoreMomentRequest`/`StoreReplyRequest`, sharing a
  `ValidDocument` rule): size/depth ceilings + reject any node/mark type outside
  `Manager::editorNodes()`.

**Status:** Decided & implemented (`k-core/src/Ux/Editor*`, `k-extensions/{composer,
discussions,reply-dock,thread-title}`). Full suite green (138 tests). `npm run build` /
`build:core-dist` both verified. Not yet browser-verified.

## 2026-07-18 — Upvotes: per-tag vote-emoji config, reusing `reactions`, `PALETTE` untouched

**Decision:** Roadmap's "Upvotes" said add 👎 to `Reaction::PALETTE` — rejected: `PALETTE`
renders unconditionally on every card's rail, so anything added there becomes a global,
always-on reaction, not a scoped feature. Instead each `Tag` gets nullable `upvote_emoji`/
`downvote_emoji` columns; whoever creates/edits a tag decides per tag whether it carries voting
and which emoji. Votes are ordinary rows in the existing `reactions` table (no new table, no
enforced mutual exclusivity between up/down — confirmed acceptable). A new `POST /_reactions/
{moment}/vote` route validates the submitted emoji against `Reaction::voteConfigFor($moment)`
(the moment's own tags' configured emoji), not `PALETTE`. A new `vote` component renders
before the generic `rail`, always showing the count (including 0); the `rail` excludes any
vote-claimed emoji via `array_diff` so the same emoji never gets two buttons.

Building this also required Tags' first admin CRUD (`k-extensions/tags` had none — public
`/tag/{slug}` page only) and a small `Kopling\Core\Ux\Modal` change: an optional explicit `$id`
constructor param, so a validation-error redirect-back can reopen the exact dialog that failed
(a hidden `_form` field + inline `showModal()` script — no new mechanism otherwise).

**Alternatives considered:** a global reactions-extension setting naming which tags get voting
(a `TagSelection` admin field) — postponed; per-tag config needs no cross-extension coordination
and is simpler to reason about.

**Status:** Decided & implemented (`k-extensions/tags` admin CRUD + `manage-tags` permission,
`k-extensions/reactions` vote route/component, `k-core/src/Ux/Modal.php`). Full suite green (167
tests). Not yet browser-verified.

## 2026-07-18 — "Top" feed sort mode (thumbs-up count only, not net score)

**Decision:** `?sort=top` reorders the feed by upvote count via a new `SortMomentsByVotes`
listener on `QueryingMoments` (same mechanism Pin's `ExcludeVisiblePinnedMoments` already uses —
no core change). Aggregates across every tag's configured `upvote_emoji` as one global list
rather than branching per-tag — realistically only one or two tags will ever carry voting.
Ordered by thumbs-up count only, not net of downvotes, matching the roadmap's own wording. A
`sort-toggle` component (Latest/Top links, plain navigation, not htmx) fills Community's
existing `content-top` slot, self-hiding when no tag configures upvoting.

**Status:** Decided & implemented (`k-extensions/reactions/src/Listeners/
SortMomentsByVotes.php`). Full suite green (167 tests). Not yet browser-verified.

## 2026-07-18 — `Ux/Form/EmojiPicker`: a Core-owned emoji picker, not a per-caller grid

**Decision:** The tags admin form's `upvote_emoji`/`downvote_emoji` fields (plain text inputs at
first) became a real emoji picker, built as a reusable Core primitive
(`Kopling\Core\Ux\Form\EmojiPicker`, `<x-k::form.emoji-picker>`) rather than a one-off in
`k-extensions/tags` -- explicitly requested as "core, any Portal can use," not scoped to this one
form. Backed by `emoji-mart`/`@emoji-mart/data` (MIT, github.com/missive/emoji-mart; both
license-checked via `npm view`/GitHub API before adding). `reactions`' own `PALETTE`-grid picker
(`modal.blade.php`) is untouched -- that's a closed-set picker for a fixed reaction palette, a
different problem from a free-choice single-emoji field.

Same dynamic-`import()` split TipTap's editor already established (`Ux/js/emoji-picker.js`, a
tiny always-loaded shim, vs. `emoji-picker-mart.js`, the real payload -- 506KB min / 110KB gzip,
same order of magnitude as `editor-tiptap.js`'s 392KB/125KB). Lazier than the editor: nothing
mounts eagerly at all, the heavy module only loads on a trigger's first *click* (event
delegation on `document`, no per-mount lifecycle to track across htmx swaps) -- appropriate
since, unlike ProseMirror, this widget has no persistent live state and today's only caller
(`/admin/tags`) is a low-traffic admin screen where most triggers on the page are never opened.
Loaded unconditionally in `head.blade.php` (not gated per-Portal) precisely because it's a Core
primitive, not owned by whichever extension happens to use it first -- same reasoning as
`editor.js`'s own unconditional load.

`emoji-mart`'s `Picker` renders into its own shadow DOM, so `emoji-picker.css` only needs to
position the popover (`position: absolute` inside a `position: relative` wrapper) -- none of its
internal styling can leak either direction. Vanilla JS throughout (`toggle()`/`onEmojiSelect`
writing straight into a hidden `<input>`), not Alpine, same ordering reasoning `editor.js`
already documents.

**Status:** Decided & implemented (`k-core/src/Ux/Form/EmojiPicker.php`, `Ux/js/emoji-picker*.js`,
`Ux/css/emoji-picker.css`, wired into both `vite.config.js`/`vite.core-dist.config.js` and
`k-extensions/tags`' admin form). `npm run build` / `build:core-dist` both verified. Full suite
green (171 tests).

**Addendum (same day):** browser-verification did surface the flagged risk -- inside
`<x-k::modal>`, the popover was appended into `container` (a normal-flow descendant of
`.modal-box`, `overflow-y: auto`), so opening it just grew `.modal-box`'s own scrollable area
instead of floating above it. Fixed by appending the popover to `container.closest('dialog') ??
document.body` instead -- a native `<dialog>` is `position: fixed; inset: 0` (daisyUI's
`.modal`), so it's never a scroll/clip ancestor, and staying inside the dialog's own DOM subtree
keeps the popover in its top-layer stacking (paints above the backdrop without needing to beat
the page's own z-index). Position is computed in JS (`getBoundingClientRect()` on the trigger,
measured post-append since emoji-mart's custom element only reports real size once rendered),
not CSS, and closes (rather than re-tracks) on scroll/resize -- same "close, don't chase"
posture Escape/click-outside already had. Full suite still green (171 tests); this specific fix
itself remains visually unverified beyond Daniël's original repro.

## 2026-07-18 — `Extend\Model::cast()` was dead for every real model; all real models now extend `Database\Model`

**Decision:** `Kopling\Core\Database\Model::getCasts()` (the override reading `Manager::models()`'s
registered casts) only takes effect for a class that actually extends `Database\Model` -- and no
real model did. `Moment`, `Tag`, `Reaction`, `Reply`, `Pin`, `Group`, `Permission`, `ThemeToken`
all extended plain `Illuminate\Database\Eloquent\Model` directly; only the test fixture
(`ModelExtender\Gadget`) extended the Kopling base, proving the mechanism worked in isolation
without ever actually being wired to anything real. `Extend\Model::cast()` has been silently
inert in production since it was built. Found while designing a fillable-extension mechanism for
tags/reactions (see the same day's "Upvotes" decision) -- fillable would have needed the
identical registry shape and inherited the same gap.

**Fix:** all eight real models now extend `Kopling\Core\Database\Model` (a one-line import
swap each, no behavior change otherwise). `Person` can't -- it must extend `Authenticatable` --
so `Database\Model`'s `getCasts()` override was extracted into a new trait,
`Database\Concerns\HasExtendedCasts`, which `Person` `use`s directly. The trait deliberately
declares no static property of its own and instead reads/writes `Database\Model::$extendedCasts`
by explicit class reference (never `static::`) -- PHP gives each trait-*consuming* class its own
independent copy of a property the trait itself declares, which would have silently given
`Person` its own permanently-empty registry instead of the one `Manager::models()` actually
populates. `Database\Model::$extendedCasts` is `public` (was `protected`) specifically so the
trait can reach it from outside the class.

**Verification added:** `ModelExtendingTest` gained a second fixture model, `Widget`, that only
`use`s `HasExtendedCasts` (mirroring `Person`'s exact constraint, not extending `Database\Model`)
-- proving the trait-only path shares the same registry a `Database\Model` subclass does, and a
third test proving two targets' casts stay isolated from each other despite the shared registry.

**Status:** Decided & implemented (`k-core/src/Database/Model.php`,
`Database/Concerns/HasExtendedCasts.php`, and an import swap in all 8 real models + `Person`).
Full suite green (175 tests, +2 from this fix specifically).

## 2026-07-18 — Moved upvote/downvote ownership from `tags` to `reactions`

**Decision:** The Upvotes implementation (this same day's earlier entry) put the
`upvote_emoji`/`downvote_emoji` schema, validation, and admin form fields directly in
`k-extensions/tags`, purely because tags already had a table/CRUD to bolt onto -- despite the
roadmap's own wording ("dual-purposed **from** `reactions`") saying reactions owns this concept.
Caught when asked to point at where it hooks into tags: it didn't hook in, it just lived there.
Full write-up of the ownership-boundary lesson: `feedback-extension-ownership-boundaries`
(agent memory). Fixed with two new, genuinely generic mechanisms rather than special-casing this
one pair of columns:

- **Migration moved** to `k-extensions/reactions/migrations/`, altering `tags`' table --
  no new mechanism needed, any extension's migration may already alter a table it doesn't own
  (`tags`' own `moment_tag` migration already does this to `moments`). Guarded with
  `Schema::hasTable('tags')` so `reactions` stays genuinely soft-dependent on `tags` in its
  migration too, matching the `class_exists` guards its runtime code already uses.
- **New `Extension\Contract\ValidatesModels`** (`modelValidationRules(): array<class-string,
  array{rules, messages}>`), aggregated by a new `Manager::modelValidationRules()` (same
  cache-aware shape every other aggregator has, wired into `CacheRegistrations`/
  `ListExtensionRegistrations`). `TagsController` merges `Tag::class`'s aggregated entry into
  its own base rules (name/slug/color) and validates once, never naming `upvote_emoji`/
  `downvote_emoji` itself.
- **`Ux\Portal\Slot` gained an optional `:context` prop** (was page-level-only, no context
  threading) -- the generic fix, not a bespoke `Footer`-style class for tags specifically. Tags'
  admin form now opens `kopling-tags::admin.tag-form`, bound to the `Tag` being edited (or no
  `:context` at all on create -- `Context::getSubject()` throws on a null subject, so the create
  form omits the prop entirely rather than passing `Context(subject: null)`). `reactions`
  registers its emoji-picker pair into that slot from its own view.
- **`TagsController::store()`/`update()` now use `Tag::forceCreate()`/`forceFill()`**, not
  `create()`/`update()` -- considered and rejected both a global `$guarded = []` (weakens mass-
  assignment protection app-wide, worse given Kopling's actual third-party-extension ambition)
  and a `fillable()` addition to `Extend\Model` (would've inherited the cast mechanism's own
  dead-code prerequisite, this same day's other entry). By the time `validated()` returns, the
  array already passed the fully-merged rule set, so bypassing `$fillable` at that one call site
  is standard Laravel practice, not a new mechanism. `Tag::$fillable` reverted to just
  `name`/`slug`/`color` -- it no longer names a concept it doesn't own.
- **Tags' admin list table dropped its upvote/downvote columns entirely** (was reading
  `$tag->upvote_emoji` directly for display) rather than leaving a smaller, still-inconsistent
  trace of the same violation. A real, visible regression (no more at-a-glance visibility of
  which tags vote) -- a proper "list column" extension point would fix it, not attempted here
  since it's a materially different (per-row, two-part: header + cell) extensibility problem
  than the create/edit form fields this refactor actually solved.

**Status:** Decided & implemented (`k-core/src/Extension/Contract/ValidatesModels.php`,
`Manager::modelValidationRules()`, `Ux/Portal/Slot.php`, `k-extensions/tags/src/Controllers/
TagsController.php`, `k-extensions/tags/src/Tag.php`, `k-extensions/reactions/src/Extension.php`
+ `views/components/tag-vote-fields.blade.php` + its own migration). Full suite green (177
tests, +2 for the new `ValidatesModels` aggregation). Not yet browser-verified.

## 2026-07-19 — Tag assignment: closed the "moment_tag only ever populated by a demo seeder" gap

**Problem:** `tags` had a full `Tag` model, browse page, and (as of yesterday) admin CRUD, but
no product path ever wrote to `moment_tag` -- only `SeedDemoTagsCommand` did, an artisan-only
demo command. Composer's own create-moment form had no tag field at all.

**Decision:** Extended the same two mechanisms from yesterday's ownership refactor rather than
inventing new ones -- this is the second real consumer proving they generalize:

- **`Extend\Model` gained a `saved()` hook**, alongside `creating()`/`saving()` -- the actual
  gap: a many-to-many sync needs the owning row's real primary key, which only exists *after*
  the insert (`creating`/`saving` both fire pre-write). `tags` registers a `saved()` hook on
  `Moment` that syncs `moment_tag` from `request('tags')` -- guarded on `request()->has('tags')`,
  not a default-to-empty read, since `saved()` fires on *every* save of a `Moment` (a future
  title-only edit, a seeder), and defaulting a missing key to `[]` would silently strip an
  unrelated save's tags. Delete-side cleanup needs nothing new: `moment_tag`'s FK columns
  already have `cascadeOnDelete()` on both sides (checked before building anything), which is
  more robust than an Eloquent `deleting` hook anyway -- it fires regardless of deletion path,
  not just Eloquent-mediated ones.
- **`Ux/Portal/Slot` reused as-is** for composer's own new `kopling-composer::compose.fields`
  slot (no `:context` -- there's no `Moment` yet during compose, same "omit rather than pass a
  null subject" rule tags' own admin form already established). `tags` fills it with a picker,
  composer stays fully ignorant tags exists.
- **`ValidatesModels` reused as-is** -- `tags` contributes an `exists:tags,id` rule for
  `Moment::class`'s `tags`/`tags.*` fields, merged into `StoreMomentRequest::rules()`. Its
  `messages()` isn't container-resolved by Laravel the way `rules()` is (`FormRequest::
  createDefaultValidator()` calls `$this->messages()` plainly, not through `Container::call()`)
  -- resolves `Manager` via `app()` directly instead of a type-hinted parameter.
- **New, genuinely reusable core primitive**: `Ux/Form/MultiSelect` gained optional `min`/`max`
  (a rendering hint only -- enforcement is a separate `ValidatesModels` rule) and a default-slot
  override for per-option markup, falling back to its existing plain-checkbox loop when no slot
  content is given. `tags`' own `select.blade.php` (colored badges) is the first real consumer
  of the slot; every existing caller (Person -> Group assignment) renders identically, unchanged.
  Min/max both `null` for this first integration (no constraint) -- picker and validation rule
  have to be updated together if that changes.

**Status:** Decided & implemented (`k-core/src/Extend/Model.php`, `Manager::models()`,
`Ux/Form/MultiSelect.php`, `k-extensions/tags/src/Extension.php` + `views/components/
select.blade.php`, `k-extensions/composer/src/Requests/StoreMomentRequest.php` +
`views/components/composer.blade.php`). Full suite green (185 tests, +7 for the new
end-to-end compose-with-tags flow, +2 for the `saved()` hook itself, +4 for `MultiSelect`'s new
capabilities). Not yet browser-verified.

## 2026-07-19 — Core/tags ownership audit: three findings, all fixed

**Audit:** Reviewed `k-core` for logic that should have lived in `tags`, and `tags` for logic
that should have lived in `k-core` (confirming the boundary from the two prior 2026-07-18/19
refactors held, before committing this branch). Three findings, all addressed:

1. **`k-core/src/Ux/Card/Tag.php` + `views/card/tag.blade.php` deleted.** A generic badge
   component literally named `Tag`, predating the real Tags extension (traced via `git log` to
   the first two commits) and completely unreferenced anywhere in the codebase. `Card\Top.php`'s
   own comment ("No `Tag` here on purpose") confirms the real tags extension deliberately never
   used it, but it was never removed -- a dead name-collision with the real `Kopling\Tags\Tag`
   domain model.
2. **`Manager::mergeModelValidationRules(string $modelClass, array $rules, array $messages =
   []): array{rules, messages}`** -- `TagsController::validated()` and `StoreMomentRequest::
   rules()`/`messages()` had independently hand-written the identical "merge my own base rules
   with whatever `modelValidationRules()` aggregated" idiom. Returns the merged pair rather than
   calling `$request->validate()` itself, since a `FormRequest`'s own `rules()`/`messages()`
   can't validate themselves -- the caller decides how to use the result.
3. **The modal-reopen-on-validation-error pattern moved from `tags`' own view into
   `Ux/Modal.php` itself.** Was entirely tag-specific-looking (a page-level `$reopening`
   variable + a shared bottom-of-page inline script) despite having nothing to do with tags --
   any admin screen with more than one modal has the identical problem. `<x-k::modal>` now
   self-reopens: any instance whose own `$id` matches a hidden `<input name="_form">`'s
   round-tripped `old('_form')` value shows itself again, with zero page-level script needed.
   Callers only need one hidden input per form, matching the modal's own `:id` -- `tags`' admin
   view and `reactions`' `tag-vote-fields.blade.php` both updated to the same convention (the
   `_form` value is now the modal's *full* id, e.g. `modal-tag-edit-{id}`, not the shorter
   `edit-{id}` key it used before this generalized).

**Debugging note worth keeping:** discovered while writing the cross-request test for #3 --
`Illuminate\Testing\TestResponse::assertSessionHasErrors()` has a side effect that clears the
`errors` session flash before a subsequent request in the same test can read it (`old('_form')`/
`_old_input` survives regardless; only `session('errors')` does not). Any future test asserting
behavior across a validation-failure request *and* a follow-up request must not chain
`assertSessionHasErrors()` on the first response -- the follow-up assertion itself is the
stronger proof anyway. Separately: `old()` reads via `request()->session()`, which only a real
HTTP request's `StartSession` middleware ever attaches -- a bare `$this->blade()` render needs
`app('request')->setLaravelSession(app('session')->driver())` called first, or `old()` always
returns the default regardless of what's in the session store directly.

**Status:** Decided & implemented (`k-core/src/Ux/Modal.php` + `views/ux/modal.blade.php`,
`Manager::mergeModelValidationRules()`, `k-extensions/tags/src/Controllers/TagsController.php`
+ `views/admin/index.blade.php`, `k-extensions/composer/src/Requests/StoreMomentRequest.php`,
`k-extensions/reactions/views/components/tag-vote-fields.blade.php`). Full suite green (189
tests, +4 for the modal self-reopen mechanism). Not yet browser-verified.

## 2026-07-19 — `Ux/Form/Combobox`: a searchable, pilled multi-select, replacing the tag picker's checkbox list

**Decision:** The tag picker built into composer's form (see the compose-with-tags entry, same
day) rendered every installed tag as a checkbox up front -- fine at today's scale, but the
wrong shape once a tag catalog grows (ships every tag to every page load, no search). Requested
directly: a Filament-style multi-select -- searched server-side, capped results, chosen values
shown as pills.

Built as a new Core primitive, `Ux/Form/Combobox` (`k-core/src/Ux/Form/Combobox.php` +
`views/ux/form/combobox.blade.php`), not a `MultiSelect` variant -- the interaction model is
fundamentally different (server-searched/paginated vs. render-every-option), so it needed a new
component rather than another prop. Same domain split as `EmojiPicker`: Core owns the widget
(pills, search input, selection state, min/max hint reusing the same convention `MultiSelect`
established), the caller owns the domain entirely through one thing -- a `searchUrl` the
component `hx-get`s (debounced, and again on focus so an empty query can still show something,
e.g. "5 most relevant"). The search endpoint's only obligation is a documented markup contract:
each result carries `data-combobox-option`/`data-id`/`data-label`; Core's own delegated `@click`
listener on the (possibly htmx-swapped) results container never looks past those three
attributes, so styling/content inside is entirely the endpoint's own choice.

Vanilla inline `x-data` (not `Alpine.data()`, same load-order reasoning `editor.js` already
documents) + htmx -- no new JS bundle, unlike `EmojiPicker`'s dynamic-imported payload, since
the interaction itself (add/remove a pill, toggle a results panel) is simple enough for Alpine
alone. Known v1 gap, stated plainly rather than pretended away: mouse/tap selection only, no
arrow-key/Enter navigation through results -- acceptable for a 5-result list, worth revisiting
if that changes.

`tags`' own picker (`views/components/select.blade.php`) shrank considerably -- it no longer
renders any tag options itself, only resolves already-selected ids to `{id, label}` pairs (for
pills to show without a round-trip) and points at its own new `GET /_tags/search` route
(`auth`-gated, capped at 5, `views/components/search-results.blade.php` fulfilling Combobox's
markup contract with a colored dot per tag).

**Bug caught while adjusting existing coverage:** the compose-page test asserting the picker
renders a tag's name was passing for the wrong reason after this swap -- the name it found
came from `widgets`' unrelated "Popular tags" sidebar, not the picker itself (which, being
search-driven now, never inlines a tag's name into the page at all). Fixed to assert the
picker's actual markup (`data-combobox-results`, the wired `hx-get` URL) instead.

**Status:** Decided & implemented. Full suite green (195 tests, +6 for `Combobox` itself and
the tags search endpoint). Not yet browser-verified -- the htmx/Alpine interaction (debounced
search, pill add/remove, click-outside-close) is the part most worth checking live; it's
reasoned through carefully here but untested outside Pest's HTML-string assertions.

## 2026-07-19 — Replaced the hand-rolled `Combobox` with `Ux/Form/TagInput`, built on `@yaireo/tagify`

**Decision:** The hand-rolled `Combobox` (this same day's earlier entry -- htmx-driven search,
inline Alpine for pill state) was rejected outright after review. Replaced with `Ux/Form/
TagInput`, built on `@yaireo/tagify` (MIT, github.com/yairEO/tagify, zero dependencies,
~20KB gzip JS + ~3KB CSS -- verified via `npm pack` before adding, same discipline every prior
dependency in this codebase got) rather than continuing to hand-roll: it's a real, mature widget
(keyboard nav, ARIA, edit-in-place all included -- closing the "known v1 limitation: no
keyboard nav" gap the hand-rolled version had to admit to), and its own "mixed tags" mode
specifically leaves room for a future inline-@mention/#tag feature typed directly into a
moment's body to reuse the same dependency later -- the deciding factor over the also-considered
`Tom Select` (Apache-2.0, similarly small, more general "enhanced select" shape but no path to
inline mentions).

Same domain split as `EmojiPicker`/the `Combobox` it replaces: Core owns the widget end to end
(`k-core/src/Ux/Form/TagInput.php` + view + `Ux/js/tag-input.js` shim + `tag-input-tagify.js`
payload, mirroring `editor.js`'s dynamic-import split -- mounts eagerly like the editor, not
behind a click like `EmojiPicker`, since a tag input needs to be interactive the moment its page
loads), the caller owns the domain through one thing -- a `searchUrl` returning JSON `{id,
label}` pairs (Tagify's own async-whitelist pattern, followed exactly as documented: null the
whitelist, `loading(true)`, replace it with the response, `loading(false).dropdown.show()`,
`AbortController` to cancel a stale in-flight request).

One real integration wrinkle: Tagify's own native behavior is to serialize the whole selection
as *one* JSON string back into its underlying input, not a plain PHP array. Every server-side
consumer already expected `request()->input('tags', [])` as a real array (from repeated
`name="tags[]"` inputs) -- rather than touch `TagsController`/`StoreMomentRequest`/the `saved()`
hook to accommodate Tagify's own format, `tag-input-tagify.js` keeps a set of real hidden
`name="{name}[]"` inputs in sync on every `add`/`remove` event instead, so the server-side
contract established earlier the same day didn't have to change at all.

Reskinned via Tagify's own documented CSS custom properties (`--tag-bg`, `--tags-border-color`,
etc.) onto the active theme's tokens, same "hand-written CSS on top of the theme's own
variables" convention `editor.css`/`emoji-picker.css` already use -- `tag-input.css` imports
Tagify's stylesheet first, then overrides.

**Status:** Decided & implemented. Full suite green (196 tests). `npm run build` /
`build:core-dist` both verified. Not yet browser-verified -- the actual remote-search/pill
interaction is the part most worth checking live, more so than usual: it's grounded directly in
Tagify's own documented API (verified against the real README, not memory) rather than
guesswork, but still untested outside Pest's HTML/JSON assertions.

## `Ux::add()` id collisions silently overwrite instead of erroring

**Decision:** `Manager::applyUxAdd()` now throws a `\LogicException` when a second `Add` entry
resolves to an id an earlier `Add` in the same registry already used, instead of silently
overwriting it.

**Why:** `tags`' own `Extension::ux()` had two `->add()` calls both landing on
`->as('tags')` (the card-body tag badge, and the admin-nav item) -- same extension, so both
resolved to the identical fully-qualified id `kopling-tags::tags`. `applyUxAdd()` stores entries
in a plain `$registry[$entry->id] = $entry` array, so the second silently replaced the first's
entire registration, including its `slot` -- the badge stopped rendering into `card.body` with
no error anywhere. The DB relation (`saved()`'s `sync()`) was never affected; this was purely a
UI-registration collision, which is what made it confusing to track down from a bug report of
"tags don't show on the card" (persistence was fine the whole time). Fixed the immediate bug by
renaming the admin-nav entry to `->as('admin-nav')`, and added the guard so the next same-name
collision fails loudly at the point it's introduced rather than silently dropping a slot
somewhere downstream. A legitimate `Ux::replace()`/`remove()` targeting an existing id is
unaffected -- only two `Add`s on the same id trip the guard.

**Status:** Decided & implemented. Full suite green (197 tests) after the guard was added;
confirmed it doesn't false-positive on any other extension's `ux()` (every other `->as()` name
in the codebase is already unique within its own extension). Also strengthened
`ComposerControllerTest`'s "assigns the submitted tags to the new moment" test to assert the tag
badge is actually present in the response HTML, not just the DB pivot row -- the real coverage
gap that let this ship unnoticed; reverting the fix locally confirmed the test now fails loudly
on this exact regression.

---

## 2026-07-19 — Extensions follow core's daisyUI theme; hand-rolled CSS is the exception, and even then must consume core's theme variables

**Decision:** Extensions style themselves through core's `<x-k::*>` components and theme tokens,
with only minor customization on top. Shipping custom CSS is the exception, reserved for cases
like wrapping a third-party widget (e.g. an image gallery) that has no core equivalent -- and
even then, that CSS must read/apply core's daisyUI CSS variables (`--color-primary`,
`--color-base-100`, etc.) rather than hardcoding its own values.

**Why:** Formalizes what `extend.html`'s existing safelisted-utilities rule already implied but
never stated outright. Hardcoded colors in extension CSS silently break runtime re-theming (an
admin re-brands via `Theme::css()` and the extension's own widget doesn't follow) and fragment
the visual "feel" as more extensions ship.

**Status:** Decided; core's own side is now enforced. `k-extensions/style-guide` (a normal,
disableable extension, not `CannotBeDisabled`) showcases every directly-invokable core
`<x-k::*>` component at `/style-guide` (gated by `access-style-guide`), and
`tests/Feature/StyleGuide/ComponentCoverageTest.php` fails CI the moment a new core component
ships without a showcase entry (reflects `k-core/src/Ux`, resolves each class's tag via the real
`ComponentTag::resolve()`, greps the style guide's own Blade source -- caught one real bug on
first run, `TextArea`'s tag being `text-area` not `textarea`). Extension-side enforcement (a lint
checking a given extension's CSS only references core's theme variables) remains unbuilt.
