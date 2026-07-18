read the following files for information about this project:
- ../kopling-landing/CLAUDE.md
- ../kopling-landing/public/index.html
- ../kopling-landing/public/charter.html
- ../kopling-landing/public/extend.html

This repository is the monorepo for the actual Kopling project. The technology follows instructions linked above.

## Git: never branch, commit, push, or open a PR without being explicitly told to

Never create a git branch, commit, push, or open a pull request unless Luceos has explicitly asked for it in that specific instance. This overrides any default "ship automatically" behavior -- including background-job conventions that would otherwise commit/push/open a draft PR without asking once code changes are made. In this repo, finish the work and say so; leave it uncommitted (in the working tree or worktree) and wait to be told to commit/branch/push/PR.

The monorepo is subsplit into readonly repositories using the .github/workflows/subsplit.yml and .github/subsplit-config.json.

## Ux components and theming

`k-core/src/Ux/` holds Kopling's re-usable, themeable UX components — the `<x-k::*>` Blade component library described in the charter — and the theming logic behind them (the daisyUI theme, CSS variables, brand tokens). Everything in this domain lives together under `Ux/`, organized by kind: `Ux/views/` (Blade templates), `Ux/css/` (Tailwind/daisyUI source), `Ux/js/` (htmx/Alpine source) — not scattered into a generic top-level `resources/`. There is no separate component-registry document yet: the charter (`public/charter.html`, Decision Log and Open Questions) is the design-system registry for now — what components exist, their contracts, and open decisions about them are tracked there until there's enough of them to justify a dedicated reference doc.

## The root Laravel installation holds no code

The root installation (`bootstrap/`, root `composer.json`, `.env`) must never contain application code — no `app/`, no `routes/`, no application-level `resources/views`. All Kopling code lives in `k-core` (core) and `k-extensions/*` (extensions). Rationale and alternatives considered: `.docs/planning/decisions.md` ("Root Laravel installation holds no application code").

`k-core` auto-registers itself with Laravel via Composer package discovery (its `ServiceProvider`), and is responsible for booting up everything the application needs around Laravel: it defines and loads its own routes (`k-core/src/routes/web.php` via `loadRoutesFrom`), its own views under the `core::` namespace (`loadViewsFrom`), and will register any further config, middleware, or bindings the same way as the codebase grows. Extensions follow the same pattern from `k-extensions/*`.

The root only owns environment bootstrapping (`bootstrap/app.php`) and the Vite/Tailwind/daisyUI build *tooling* (`package.json`, `node_modules`, the `vite*.config.js` files) — not business logic, and not source assets either. Tailwind scans `k-core` and `k-extensions` Blade files for classes from there.

## Source assets live inside each package, same as its PHP/Blade — root only points at them

`k-core`'s own CSS/JS source lives at `k-core/src/Ux/css/app.css` and `k-core/src/Ux/js/app.js` — alongside `Ux/views/`, not in a generic top-level `resources/`. The root `package.json`/`vite.config.js`/`vite.core-dist.config.js` are a single shared Node toolchain for convenience (one `node_modules`, not one per package), but every config points *into* the owning package's own source tree and *into* that package's own `dist/` for output — the root itself owns no source files. When an extension eventually needs its own compiled CSS/JS, it organizes its own source the same domain-driven way (wherever its own structure puts UI concerns, e.g. its own `Ux/`) plus its own dedicated Vite config/output — not a shared bundle. Rationale and alternatives considered: `.docs/planning/decisions.md` ("Source assets live inside the owning package's own domain folder...").

## How k-core ships compiled assets (Node/npm never runs on a Kopling host)

The root Vite build (`vite.config.js`, `public/build/*`, sourced from `k-core/src/Ux/**`) is monorepo-dev-only, consumed via Laravel's `@vite()`. It is **not** how a real Kopling site gets its CSS/JS: `vite.core-dist.config.js` (`npm run build:core-dist`) and `.github/workflows/release.yml` compile `k-core/src/Ux/css/app.css` + `Ux/js/app.js` with fixed, unhashed filenames into `k-core/dist/app.css` + `app.js` at release time (`workflow_dispatch`, not on every push), commit that to `main`, then tag the release — which triggers `subsplit.yml`'s `create: tags:` handler, carrying the compiled assets into `kopl.ing/core`. Node/Vite only ever run in GitHub Actions, at release time — never on a Kopling site's host. Extensions don't get their own compiled bundle yet: they're limited to a safelisted Tailwind utility subset + `<x-k::*>` components, both already inside `k-core`'s one compiled bundle, or plain hand-written static CSS (no compilation involved) for rare custom needs — a per-extension `resources/`+`dist/`+release workflow (mirroring `k-core`'s) is the documented path for when one needs more than that. Rationale and alternatives considered: `.docs/planning/decisions.md` ("k-core ships precompiled CSS/JS as committed release artifacts...").

**TODO, not yet built:** `k-core`'s layout (`k-core/src/Ux/views/layouts/app.blade.php`) still calls `@vite()` unconditionally, which throws `ViteManifestNotFoundException` for anyone who installs `kopling/core` standalone outside this monorepo (no `public/build/manifest.json` exists there). It should auto-detect and fall back to the shipped `k-core/dist/app.css`+`app.js` when no monorepo Vite manifest is present — deferred for now, do this before `kopling/core` is meant to be installed anywhere but this monorepo.

## Extension conventions

Documented publicly at `../kopling-landing/public/extend.html` — a living document of how to build a Kopling extension (paths, conventions, the `AbstractExtension` contract, icon, etc.), not here. `k-extensions/example` is the working reference implementation of everything that page describes; keep the two in sync when either changes. Full technical rationale for the design (why `AbstractExtension` isn't a `ServiceProvider`, the naming-collision reasoning, alternatives considered): `.docs/planning/decisions.md`.

## Feature ownership across extensions

A feature belongs to the extension whose *domain concept* it is — never to whichever extension happens to already have a convenient table, model, or controller to bolt it onto. Before writing schema, validation, or UI for a cross-cutting feature, check the owning extension's own `Extension::description()` and the roadmap/charter wording (e.g. "dual-purposed from X" names X as the owner) — implementation convenience never decides ownership.

Concrete test: with the *other* extension uninstalled, would this extension's migration/controller/view still read as entirely its own domain, with no mention of the other's feature by name? If the code can't be explained without naming that other extension's concept, it belongs there instead, reached into this one through an existing extensibility mechanism:

- `Extend\Model` — relations, casts, `creating`/`saving` hooks on a model you don't own
- `Extension\Contract\ValidatesModels` — extra validation rules/messages for a model you don't own, aggregated via `Manager::modelValidationRules()`
- `ChangesUx` — a slot on a page or form you don't own; `Ux\Portal\Slot` takes an optional `:context` prop for slots bound to a specific record (a `Tag` being edited, say), not just page-level chrome
- `ExtendsPortals` — routes/css/js attached to a Portal you don't own
- `HasPermissions` — permissions, always declared locally then Manager-prefixed, never a raw cross-extension string

If the specific hook needed doesn't exist yet, that's a gap to close in core — a new contract, same shape as the ones above — never a reason to write the other extension's concept directly into your own files just because that's where the convenient CRUD already lives. Full incident and the fix that established this rule: `.docs/planning/decisions.md`, 2026-07-18 ("Moved upvote/downvote ownership from `tags` to `reactions`") — upvote/downvote emoji were built directly into `tags` because it already had the admin CRUD, despite the roadmap itself saying reactions owns the concept.

## Recording decisions: charter vs. this repo's decision history

The charter (`public/charter.html`, Section 12 Decision Log) is where **major, project-wide** decisions get proposed and recorded — plain language, public-facing, one-line rationale (existing working agreement: propose the diff there rather than letting a real decision live only in chat or commit messages).

That's deliberately too coarse to carry full engineering detail, so this repo also keeps **`.docs/planning/decisions.md`** — a technical decision history, more granular and more technically worded than the charter, covering *every* decision worth remembering, major or minor, not just the ones that rise to charter-level. Record: the decision, why (the actual reasoning/trade-off, not just the outcome), alternatives considered and why they were rejected, and status. The charter tells a public reader *what* Kopling decided; this file tells a future contributor (or agent) working in this codebase *why*, in as much technical depth as the decision actually needed — so the "why" doesn't quietly get lost behind just "how" the code ended up looking, a year from now.

Add an entry to `.docs/planning/decisions.md` whenever a non-trivial technical decision gets made in this repo, whether or not it's also charter-worthy.

## Tests

`vendor/bin/pest` runs the suite (Pest 4, root `tests/`, config in `tests/Pest.php`). Two layers:
- `tests/Unit/` — pure extensibility-mechanism tests, no app boot. Use the `fakeManager(array $extensions)` helper (`tests/Pest.php`) with `tests/Support/FakeManifest` and disposable fixture extensions under `tests/Fixtures/Extensions/*` to control exactly what `Manager` discovers, independent of whichever real extensions happen to be installed.
- `tests/Feature/` — anything needing a booted app (real Gate, real routes, real Blade/`ComponentTag` resolution — see the `Manager::ux()` gotcha below). Swap `$this->app->instance(Manager::class, fakeManager([...]))` mid-test for the same fixture-based control at the HTTP level.

CI: `.github/workflows/tests.yml` runs this on every push to `main` and every pull request.

## Gotchas & environment notes

- Never mark a class or method `final` anywhere in this codebase — the override/escape-hatch path (outlets, extension points) only stays real if nothing is ever sealed shut.
- `composer dump-autoload` does not re-read a path-repo's `composer.json` (a new `"type"` or `extra` key, etc.) — run `composer update <package>` to force Composer to pick it up.
- Any new Vite build config needs `publicDir: false` if its `outDir` isn't `public/build` — otherwise Vite silently copies the whole `public/` folder (including Laravel's own `index.php`) into it.
- `Manager::extensions()` always instantiates discovered extension classes with `new $class()` — no constructor args ever passed. Extensions (and test fixtures under `tests/Fixtures/Extensions/`) must work with a no-arg constructor.
- `Manager::ux()` always touches Core's real Blade component classes (`Top`/`Footer`/`Body`/`Sidebar`/`ThemeSwitcher` defaults), which resolve through `ComponentTag`/the `Blade` facade — needs a booted app, so it can never be exercised in a bare unit test with no container (use a Feature test instead).
- Don't run `cp .env.example .env` / `php artisan key:generate` against this actual dev checkout to dry-run a CI change — it overwrites the real local `.env`/`APP_KEY`. Copy the repo (excluding `vendor`/`node_modules`/`database`/`.git`) to a scratch dir and run it there instead.

