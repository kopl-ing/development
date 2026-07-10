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
interface per surface. `Kopling\Core\Ux\Ux` is a fluent builder mirroring Laravel's own
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

**Decision:** Three additions to `Kopling\Core\Ux\Ux`, all building on the same fluent-builder
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

## 2026-07-10 — `kopling:extension:registrations`: a debugging command, not a public API, reads Manager's own collectors rather than re-deriving anything

**Decision:** `Kopling\Core\Console\Commands\ListExtensionRegistrations` (`php artisan
kopling:extension:registrations {example|kopling/example|core}`) prints everything one installed
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
