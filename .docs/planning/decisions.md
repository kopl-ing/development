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
