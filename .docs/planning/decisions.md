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
