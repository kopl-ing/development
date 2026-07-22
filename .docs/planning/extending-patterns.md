# Extending k-core: a patterns reference

This is a technical catalog of every mechanism `k-core` offers for extending it — one entry
per mechanism, what it's for, a minimal example, and which real extension under
`k-extensions/` demonstrates it. It's aimed at contributors working in this codebase (human
or agent) who need to answer "what are my options for X" quickly, at whatever depth they
need — skim the table in Section 1 for an overview, or read a full section for detail.

This overlaps on purpose with `../kopling-landing/public/extend.html`, the public-facing
guide for third-party extension authors. That page teaches you how to *write* an extension,
step by step. This file instead catalogs the mechanisms themselves as a reference, with
pointers into `k-core`'s actual source so the "why" behind each one is one click away. If the
two ever disagree, the code is right and both docs are stale.

No prior Laravel/Kopling knowledge is assumed beyond what's explained inline. Jargon (Gate,
Eloquent, service provider, etc.) is explained the first time it's used.

## 1. The big picture

Every extension is one PHP class, `Extension.php`, extending
`Kopling\Core\Extension\AbstractExtension` (`k-core/src/Extension/AbstractExtension.php:17`).
It has no constructor arguments — `Manager` always instantiates it with `new $class()` — and
must implement `name()` and `description()`. Everything else it does is opt-in: implement zero,
one, or many of the *contract interfaces* below, and `Kopling\Core\Extension\Manager`
(`k-core/src/Extension/Manager.php`) discovers what you implemented and wires it into the
running app.

There's a second category that needs no interface at all: **directory conventions**. If your
extension ships a `migrations/`, `views/`, or `lang/` directory, `Manager::conventions()`
(`Manager.php:99`) finds it by its path alone and registers it with Laravel — no class, no
method, just the directory existing.

Quick-reference table — every mechanism, in the order this document covers them:

| Mechanism | Kind | Purpose | Real example |
|---|---|---|---|
| `migrations/`, `views/`, `lang/` dirs | directory convention | database tables, Blade templates, translations | any extension |
| `ExtendsModels` | contract | add relations/casts to a core model | `reactions`, `tags`, `discussions` |
| `HasPermissions` | contract | declare a named, gated capability | `example`, `admin`, `discussions` |
| `ChangesUx` | contract | place UI into a named slot | `example`, `reactions`, `tags` |
| `HasPortals` | contract | register a new page/section | `admin`, core's own `community` |
| `ExtendsPortals` | contract | attach routes/css/js to a page | `example`, `reactions`, `discussions` |
| `ChangesTheme` | contract | ship a colour/radius theme | `theme-delft`, `theme-midnight` |
| `RequestsStorageDriver` | contract | declare a file-storage need | `example` (illustrative only) |
| `HasCommands` | contract | register an artisan command | `reactions`, `tags`, `discussions` |
| `ListensToEvents` | contract | react to a core event | `auth-email-password` |
| `LoadsAfter`/`LoadsBefore` | contract | pin your own load order relative to named packages | `example` (illustrative only) |
| `InfluencesLoadOrder` | contract | require load order for anything implementing a contract you own | none shipped yet |
| `CannotBeDisabled` | marker contract | refuse a future "disable this extension" toggle | `Core`, `admin` |

## 2. Directory conventions (no code required)

If these directories exist at your extension's root, `Manager::conventions()` finds and
registers them automatically — nothing to implement:

- `migrations/` — plain Laravel migration files, loaded via `loadMigrationsFrom()`.
- `views/` — Blade templates, loaded via `loadViewsFrom()` under your extension's own
  namespace (see Section 8 on naming) — reference them as `your-id::some.view`.
- `lang/` — translation files, loaded via `loadTranslationsFrom()`, same namespace —
  reference strings as `__('your-id::file.key')`.

Routes, CSS, and JS are deliberately **not** on this list — see `ExtendsPortals` (Section 6):
attaching them always needs a target Portal (which route group, which page's `<head>`), so a
bare "the directory exists" rule can't express that the way it can for
migrations/views/lang.

## 3. Model extensions — `ExtendsModels`

Lets your extension add an Eloquent relation (Laravel's term for a method like `hasMany()` /
`belongsTo()` that returns related rows), a cast (how a raw database column value is
turned into a PHP type), or a creating/saving hook onto a **core** model — like `Moment` or
`Reply` — without ever touching that model's own class.

```php
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extension\Contract\ExtendsModels;

public function models(): array
{
    return [
        (new Model(Moment::class))
            ->relation((new Relation)->hasMany('reactions', Reaction::class)->eagerLoad()),
        (new Model(Reply::class))
            ->creating(fn (Reply $reply) => $reply->ip = request()->ip())
            ->saving(fn (Reply $reply) => $reply->body = TemplateHooks::render($reply->body)),
    ];
}
```

`->eagerLoad()` marks the relation to be batch-loaded up front for every `Moment` in a feed,
instead of firing one query per card when it's read — the difference between one query and
one-per-row on a page with many cards. It accepts `true`, `false`, or a callable if the
decision depends on the current portal/request/actor.

`->creating()` / `->saving()` register straight onto the target model's own native Eloquent
`creating`/`saving` events. `creating` fires once, on insert only — the right fit for setting a
value only relevant at creation, like an IP. `saving` fires on both insert and update — the
right fit for sanitizing/transforming content, since it should also apply if the row is later
edited. Both need no base-class change on the target model — unlike `cast()` above, they work on
any Eloquent model. The closure gets the model instance as its only argument (Eloquent's own
fixed shape for these events, not the `bool|callable(Portal, Request, Person)` shape
`linksTo()`/`eagerLoad()` use); return `false` from a `creating()` closure to cancel the insert
outright. Each is a single slot, not an accumulating list — one declaration needs at most one
`creating` and one `saving` hook — but two extensions targeting the same model each get their
own declaration, and both fire, in load order, since Eloquent supports multiple listeners per
event natively.

Two extensions can both add relations to the same model; they combine. A relation-name clash
is last-registered-wins; core's own casts always win over an extension's; `creating`/`saving`
hooks never collide, since every extension's hook for the same model/event fires.

See it in: `k-extensions/reactions/src/Extension.php:48`, `k-extensions/tags/src/Extension.php:63`.

## 4. Permissions — `HasPermissions`

A named, gated capability — never a hardcoded "is this person an admin" check. You write just
the local id (e.g. `"manage-things"`); `Manager` prefixes it to
`"your-extension-id::manage-things"` before it's registered, so you never have to worry about
colliding with another extension's permission of the same name (see Section 8).

```php
use Kopling\Core\Authorization\Permission;
use Kopling\Core\Extension\Contract\HasPermissions;

public function permissions(): array
{
    return [
        new Permission(
            id: 'manage-things',
            label: __('kopling-example::permissions.manage-things.label'),
            description: __('kopling-example::permissions.manage-things.description'),
        ),
    ];
}
```

Under the hood this becomes a Laravel [Gate](https://laravel.com/docs/authorization#gates) —
a named check you can later ask "can this person do X?" via `Gate::allows('your-id::manage-things')`
or the `@can` Blade directive. The base check is "does this person hold the permission via one
of their groups"; `Permission::$callback` is an optional *extra* condition layered on top
(e.g. "and they must own this specific record") — it can add a restriction, never grant access
on its own. `Permission::$default` sets the fallback when no group grants it either way.

See it in: `k-extensions/example/src/Extension.php:69`, `k-extensions/discussions/src/Extension.php:61`
(shows `default: true`).

## 5. UI placement — `ChangesUx`

The most-used mechanism: one contract for putting a piece of UI into *any* named slot —
today, the side navigation and a Moment card's body/footer; more surfaces (head assets, admin
widgets) later. There's one contract for all of them, not a separate one per slot.

```php
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Portal\Navigation\Item;

public function ux(): Ux
{
    return Ux::make()
        ->add(Item::class, ['label' => 'Hello', 'route' => 'kopling-core::community/example.hello'])
        ->in('kopling-core::side-navigation')
        ->as('hello')
        ->when('manage-things');
}
```

Reading the chain:

- `add($component, $data = [])` — registers a Blade component. `$component` is either a class
  name (resolved to its component tag automatically) or an already-valid tag string like
  `"kopling-example::rail"`. `$data` is static config passed to it as a single `data` prop.
- `->in($slot)` — which slot this renders into. Slot names are fully-qualified strings you
  write out in full (e.g. `"kopling-core::card.footer"`) since they're a shared name other
  extensions reference too — never auto-prefixed.
- `->as($id)` — gives this entry a stable name other extensions can anchor to. Defaults to the
  component name if omitted.
- `->after($id)` / `->before($id)` — position relative to another entry in the same slot, by
  its fully-qualified id. Pointing at an entry that doesn't exist (extension not installed, or
  removed) is silently ignored — never an error. This is what lets extensions compose without
  knowing whether each other is installed.
- `->when($condition)` — show only if a permission id (a string, prefixed the same way
  `Permission::$id` is) or a closure `fn(?Person $person): bool` passes.

Two more operations exist for reaching into *another* extension's registration by its
fully-qualified id:

- `->replace($id, $component, $data)` — swap what an already-registered entry renders, keeping
  its position. A no-op if the target doesn't exist.
- `->remove($id)` — delete an already-registered entry outright. Same no-op-if-missing rule.

Both only affect entries registered by an extension that ran *before* yours (load order,
Section 9) — you can't replace/remove something registered after you.

Components registered anonymously (a tag string, not a class) read the render-time binding via
`$context` — e.g. `$context->subject` for "which Moment is this card about" — rather than
needing props threaded through by hand.

See it in: `k-extensions/example/src/Extension.php:87` (add + when), `k-extensions/tags/src/Extension.php:36`
(before), `k-extensions/discussions/src/Extension.php:40` (after, with a dangling anchor that's
fine if `reactions` isn't installed).

## 6. Pages — `HasPortals` and `ExtendsPortals`

A **Portal** is a named page/section — a route prefix plus the Blade layout its pages render
inside (e.g. Core's own `community` portal, or `admin`'s `admin` portal). It's deliberately
**not** an authorization mechanism by itself — routes registered under a Portal still check
their own permission exactly as they would anywhere else. This split is two contracts, not one,
because "declaring a page exists" and "putting something under it" are different concerns —
notably, the extension that declares a Portal doesn't get to skip declaring its own attachment
either; there's exactly one path for both.

**`HasPortals`** declares a Portal's identity only — no routes, no code:

```php
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;

public function portals(): array
{
    return [
        new Portal(
            id: 'admin',
            label: 'Admin',
            path: 'admin',
            layout: 'kopling-admin::layouts.admin',
            permission: 'access-admin',
        ),
    ];
}
```

**`ExtendsPortals`** is the *only* way anything actually attaches to a Portal — routes, plain
hand-written CSS, plain hand-written JS — targeting any Portal, whether your own or someone
else's:

```php
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Portal\PortalExtension;

public function extendsPortals(): array
{
    return [
        new PortalExtension('kopling-core::community')
            ->routes(__DIR__.'/../routes/web.php')
            ->css(__DIR__.'/../css/app.css')
            ->js(__DIR__.'/../js/app.js'),
    ];
}
```

Routes ride the target Portal's own route group, inheriting its URL prefix, route name
prefix, and middleware for free. CSS/JS are plain files (no build step) linked onto the page
via a `<link>`/`<script>` tag whenever the current request resolves to that Portal.

**XHR/htmx-action routes** — any route that's purely an AJAX action target, never a page a
person navigates to, links to, or bookmarks directly — a JSON search/autocomplete/lookup call
just as much as an `hx-post` form target that only ever swaps in a fragment — gets a dedicated
path shape so these don't grow wild in whatever free-form paths each extension happens to pick:
`_xhr/{extension-id}/...`, where `{extension-id}` is the same fully-prefixed id used everywhere
else (Section 13):

```php
// Inside routes/web.php, attached via ExtendsPortals like any other route.
Route::get('_xhr/kopling-example/search', SearchController::class)->name('search');
Route::post('_xhr/kopling-example/{thing}/react', ReactController::class)->name('react');
```

`_xhr` leads (not the extension-id) so every action-only endpoint attached to a Portal clusters
together in `route:list` output — immediately separable from real, navigable pages. The one
exception is `hx-boost` on a link to a real page (e.g. `page/pagination.blade.php`) — that's the
same URL a full navigation would hit, just boosted, so it has to stay on its own natural
resource path; moving it under `_xhr` would duplicate the route or break bookmarkability. The
test is "is this URL ever a real page," not "does it return JSON" — see
`.docs/planning/decisions.md`, 2026-07-22, for the full reasoning.

See it in: `k-extensions/reactions/routes/web.php` (`_xhr/kopling-reactions/{type}/{id}`),
`k-extensions/poll/routes/web.php` (`_xhr/kopling-poll/{poll}/vote`),
`k-core/routes/community.php` (`_xhr/kopling-core/icon-search`, `_xhr/kopling-core/moments/latest`).

Putting your *own* UI into a Portal you don't own (a link in Admin's side navigation, say) is
`ChangesUx` (Section 5), not this — most extensions place a few things into an existing
Portal and never register one of their own.

See it in: `k-extensions/admin/src/Extension.php:58` (HasPortals), `k-extensions/example/src/Extension.php:103`
and `k-extensions/reactions/src/Extension.php:92` (ExtendsPortals).

## 7. Theming — `ChangesTheme`

Ships a named theme: a set of CSS custom-property overrides layered on top of the compiled
base daisyUI theme. Keys must be one of `Kopling\Core\Ux\Theme\Token`'s cases (an unrecognized
key or a value in the wrong shape throws immediately — an author typo, not something to
degrade around); anything you don't mention keeps the compiled default.

```php
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\Token;

public function theme(): array
{
    return [
        Token::ColorPrimary->value => '#2b4a9b',
        Token::RadiusBox->value => '1rem',
    ];
}
```

There's no "pick one active theme among several installed" selection yet — every installed
theme's tokens simply merge together, last-declared wins on overlap.

See it in: `k-extensions/theme-delft/src/Extension.php`, `k-extensions/theme-midnight/src/Extension.php`.

## 8. Storage — `RequestsStorageDriver`

Declares a named file-storage *need* — never a backend (local disk, S3, etc.). The actual
backend is chosen later by whoever's administering the install, mapping your named request to
a configured storage drive.

```php
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Storage\{StorageAccess, StoragePermission, StorageRetention, StorageRequest};

public function storage(): array
{
    return [
        new StorageRequest(
            id: 'avatars',
            label: 'Avatars',
            description: 'Profile pictures uploaded by members.',
            access: StorageAccess::Public,      // Private | Public | Signed (temporary URL)
            retention: StorageRetention::Persistent, // Cache (purgeable) | Persistent
            permission: StoragePermission::ReadWrite, // ReadOnly | ReadWrite
        ),
    ];
}
```

See it in: `k-extensions/example/src/Extension.php:46` (illustrative — not wired to real
functionality yet).

## 9. Commands — `HasCommands`

Registers one or more [artisan commands](https://laravel.com/docs/artisan) (Laravel's CLI task
system) with the app. Return class-strings, not instances — Kopling instantiates them.

```php
use Kopling\Core\Extension\Contract\HasCommands;

public function commands(): array
{
    return [SeedDemoReactionsCommand::class];
}
```

See it in: `k-extensions/reactions/src/Extension.php:34`, `k-extensions/tags`, `k-extensions/discussions`.

## 10. Events — `ListensToEvents`

Maps a core event class to a listener class (or an event subscriber). Kopling resolves and
calls it through the container — the normal Laravel way, just declared here instead of a
service provider.

```php
use Kopling\Core\Extension\Contract\ListensToEvents;

public function listen(): array
{
    return [
        AttemptLogin::class => AttemptPasswordLogin::class,
    ];
}
```

See it in: `k-extensions/auth-email-password/src/Extension.php`.

## 11. Load order — `LoadsAfter`/`LoadsBefore` and `InfluencesLoadOrder`

By default, extensions load in alphabetical-by-package order (deterministic, but otherwise
meaningless). Two ways to change that when order actually matters — e.g. you need another
extension's Portal to already be registered before you attach to it:

**`LoadsAfter`/`LoadsBefore`** — self-declared, by package name. The explicit escape hatch:
always wins over anything `InfluencesLoadOrder` infers for the same pair. Two separate
single-method interfaces, not one — implement only the direction you actually need (the common
case is just one), instead of being forced to declare a no-op for the other:

```php
use Kopling\Core\Extension\LoadOrder\LoadsAfter;

public function loadAfter(): array { return ['kopling/admin']; }
```

A reference to a package that isn't installed is ignored, never an error.

**`InfluencesLoadOrder`** — the inverse direction: if you *own* a contract (say a future
`HasSettings` interface belonging to `kopling/admin`), you can require every extension that
*implements* it to load after/before you, without ever knowing which packages will implement
it.

```php
use Kopling\Core\Extension\LoadOrder\{Directive, InfluencesLoadOrder};

public function loadOrderRules(): array
{
    return [HasSettings::class => Directive::After];
}
```

See it in: `k-extensions/example/src/Extension.php:129` (`LoadsAfter`, illustrative).
`InfluencesLoadOrder` has no shipped implementor yet — `kopling/admin` is the intended first,
once a `HasSettings`-style contract exists worth requiring order against.

## 12. `CannotBeDisabled` (marker contract)

No methods — just a flag. No admin-facing "disable this extension" toggle exists yet, but
whenever one is built, it must refuse for anything implementing this. Covers both Core itself
(not a real "disableable" extension) and a hosting provider bundling an extension they don't
want an admin able to turn off.

```php
use Kopling\Core\Extension\Contract\CannotBeDisabled;

class Extension extends AbstractExtension implements CannotBeDisabled { /* ... */ }
```

See it in: `Kopling\Core\Core` (`k-core/src/Core.php:31`), `k-extensions/admin/src/Extension.php:29`.

## 13. Naming and collision safety

One rule threads through almost every mechanism above: you write a **local** id
(`"manage-things"`, `"rail"`, `"admin"`), and `Manager::id($package)`
(`Manager.php:135`) prefixes it with your extension's own id — its Composer package name with
`/` replaced by `-` (e.g. `kopling/example` → `kopling-example`) — before it's ever registered.
This applies to permission ids, Portal ids, UxEntry ids/conditions, and storage request ids.
The result: two extensions can both declare a permission called `"manage-things"` and never
collide, because they end up as `kopling-example::manage-things` and
`acme-other::manage-things`.

References that point at *someone else's* fully-qualified id — `Ux::after()`/`before()`,
`Ux::replace()`/`remove()`'s target, `PortalExtension`'s target Portal, `LoadsAfter`/
`LoadsBefore`'s package names — are written out in full by the author and never auto-prefixed,
since they're foreign references, not something you own the naming of. Every one of these
degrades gracefully: pointing at something that doesn't exist (extension not installed, typo,
removed) is a silent no-op, never an error. This is deliberate — it's what lets extensions
compose without needing to know whether each other is installed.

## 14. How it's actually wired up (for the curious)

You don't need this to write an extension, but it explains where the above actually runs:

1. `Manifest` (`k-core/src/Extension/Manifest.php`) reads Composer's `installed.json` at boot,
   filters for packages declaring `"type": "kopling-extension"`, and caches the result.
2. `Manager::extensions()` instantiates each one (`new $class()`), always with `Core` pinned
   first, then orders them via `LoadOrder\Resolver` (Section 9's mechanism, Kahn's algorithm
   under the hood).
3. `Provider\ServiceProvider::boot()` (`k-core/src/Provider/ServiceProvider.php:46`) — a
   Laravel [service provider](https://laravel.com/docs/providers) (the standard place Laravel
   packages register themselves with the framework) — runs once per request-cycle boot: it
   registers directory conventions, defines every permission as a Gate, and calls
   `$manager->listeners()` / `$manager->models()` for their side effects.
   `Manager::ux()` / `portals()` / `portalExtensions()` etc. are read later, on demand, by
   whatever's rendering (a Blade view, a route).

## See also

- `../kopling-landing/public/extend.html` — the public step-by-step guide to writing an
  extension (composer.json shape, directory layout, icon sizes).
- `k-extensions/example` — a working, verified extension exercising every mechanism except
  `HasPortals`, `ChangesTheme`, `ListensToEvents`, and `InfluencesLoadOrder` (see Sections
  6/7/10/11 for where those live instead).
- `decisions.md` — the "why" behind the extension system's overall shape (e.g. why
  `AbstractExtension` isn't a `ServiceProvider`).
