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

## Prefer daisyUI components over hand-rolled UI

Before building custom markup/CSS for a piece of UI, check whether a daisyUI component (https://daisyui.com/components/) already covers it — its component classes, modifiers, and utility hooks (masks, sizes, `-group` wrappers, state modifiers) first, bespoke CSS only for what daisyUI genuinely has no equivalent for. This applies inside `k-core/src/Ux/` and to extension-local CSS (`k-extensions/*/css/app.css`) alike — a one-off `.kop-*` class is for filling a real gap, not a substitute for a daisyUI class that already does the job.

## Directory structure: ask before restructuring

Component/package trees (`Ux/`, an extension's `src/`, `views/`) should stay balanced — not so flat that one directory accumulates many unrelated files, not so deep that individual components each get their own directory for no reason. When it's not obvious which existing directory a new component belongs to (or whether it warrants a new one), ask rather than assume — this is a judgment call worth getting right once rather than drifting.

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

`.docs/planning/decisions.md` is this repo's technical companion — more granular and more technically worded than the charter, but **a decision log, not a changelog**. It records choices that shape how future work gets built: a new convention, a contract, an architectural pattern, or a reversal of a prior entry. It does not record routine feature work, bug fixes, or layout/CSS tweaks — those belong in commit messages and PR descriptions, even when they took real effort to get right. The test: would a future contributor benefit from knowing this was decided on purpose, for this reason, rather than reverse-engineering it from the code? If yes, it's an entry; if it's just what got built this session, it isn't.

Keep each entry short: **Decision** (1-2 sentences), **Why** (1-2 sentences), **Status** (a phrase). Skip "alternatives considered"/"trade-off accepted" as standard fields — include either, in one sentence, only when the rejected alternative is genuinely load-bearing for a future reader's judgment call. Never include test-run counts or "verified by..." narration; that's what CI and commit messages are for.

Append-only applies across sessions and days — a decision made previously, later reversed, gets a new entry linking back to the one it replaces. It does not apply within one working session: if a decision gets superseded before it ever shipped as the real answer, correct the entry in place rather than keeping the abandoned intermediate step as its own entry.

Add an entry only when a non-trivial technical decision (per the test above) gets made in this repo, whether or not it's also charter-worthy. When a fix or cleanup surfaces an adjacent, out-of-scope issue, don't unilaterally decide "leave it, noted in decisions.md" and move on — ask what to do with it (fix now, defer, or leave) before writing anything down.

## Comments and docblocks: default to none

Default to no comment. Code ships to production and gets loaded (by tooling, by AI agents reading it, by every future contributor) whether or not anyone asked for the essay — a docblock is not a substitute for self-explanatory naming, and it isn't free just because it "might help later." Write one only when a reader at that exact line would otherwise get it wrong: a real browser/CSS quirk, an ordering dependency, a "don't touch X without also touching Y" warning. One or two sentences, not a design retrospective.

Why a decision was made, alternatives considered, or how a feature evolved belongs in `.docs/planning/decisions.md` (if it clears that file's own bar, see above) or the commit/PR message — never a docblock. A comment that encodes a reasoning chain (e.g. "this works because CSS stacking does X") is also the most expensive kind to get wrong: if the underlying assumption turns out mistaken, both the code and the prose explaining it need fixing, not just the bug. Where a behavior actually matters, a test asserting it beats a paragraph describing it — tests don't rot silently the way comments do.

This applies retroactively too: if you're already touching a file and its existing comments are essay-length, trim them to fit this convention rather than adding to the pile — but don't go out of your way to rewrite comments in files you have no other reason to touch.

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
- `npm run dev` serves assets live from source — `public/build` is only written by `npm run build` and is dead leftover the moment a dev server is running. Check for `public/hot` before assuming a source edit hasn't taken effect.
- A bundled JS widget re-themed via CSS custom properties (editor/emoji-picker/icon-picker/tag-input) may render part of itself (e.g. a dropdown) as a direct child of `<body>`, escaping any variable scoped to the widget's own root class — set those at `:root` instead, and check daisyUI's compiled CSS for a name collision (e.g. `--input-color` is reused, unrelated, across several daisyUI components) before trusting a third-party variable name is safe unscoped.
- `php -l` doesn't validate Blade syntax (`.blade.php` isn't valid PHP) — confirm a Blade edit didn't break rendering via the Pest suite, not a lint check.
- An anonymous Blade component's tag must not collide (kebab-case tag ↔ PascalCase class) with any class in its own extension's namespace — `Blade::componentNamespace()` guesses a class from the tag before ever falling back to the anonymous view, so e.g. registering `'kopling-poll::poll'` as a slot entry silently resolves to a `Kopling\Poll\Poll` *Eloquent model* instead of `views/components/poll.blade.php`, if that model happens to exist. Name the view something that can't collide with a model/class in the same extension (`widget`, `card`, etc.).
- A newly-installed extension is not automatically enabled once any extension has ever been toggled on/off (`kopling:extensions:enable`/`disable`, `EnabledExtensions`) — `all() === null` (nothing ever toggled) means everyone's enabled, but once that list exists, a brand-new package silently doesn't load until explicitly enabled. Check `kopling:extensions:list` before assuming a freshly-added extension not appearing anywhere is a real bug.

