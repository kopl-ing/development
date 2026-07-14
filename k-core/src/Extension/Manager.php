<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;
use Kopling\Core\Core;
use Kopling\Core\Database\Model as DatabaseModel;
use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\ListensToEvents;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Extension\LoadOrder\Resolver;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Storage\StorageRequest;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Theme\Token;
use Kopling\Core\Ux\UxAction;
use Kopling\Core\Ux\UxEntry;

class Manager
{
    protected ?Collection $models = null;

    /**
     * @var array<string, AbstractExtension>|null
     */
    protected ?array $extensions = null;

    public function __construct(
        protected Manifest $manifest,
        protected Dispatcher $events,
    )
    {
    }

    /**
     * `Core` (keyed `'kopling/core'`, its real Composer package name) is always the first
     * entry, guaranteed present -- it isn't Composer-discovered the way the rest are (it
     * declares no `"type": "kopling-extension"` package of its own), it's the one thing
     * `Manager` always loads regardless. Every other entry is a genuinely discovered
     * extension, keyed by Composer package name, instantiated once, then ordered by
     * `LoadOrder\Resolver` -- Composer's own `installed.json` order carries no meaning beyond
     * being the alphabetical tie-break base `Resolver::resolve()` sorts from.
     *
     * TODO: every discovered extension is treated as enabled, unconditionally -- there is no
     * "disabled" state at all yet, for any extension. Fine while every installed extension is
     * something you deliberately chose to `composer require` (true today), a real gap once
     * enabling/disabling an installed extension without uninstalling it is expected to exist
     * (an admin toggle). `CannotBeDisabled` (Contract/CannotBeDisabled.php) already guards
     * that future toggle -- the toggle itself isn't built, and this method is where its
     * filtering would go once it is.
     *
     * @return array<string, AbstractExtension>
     */
    public function extensions(): array
    {
        if ($this->extensions !== null) {
            return $this->extensions;
        }

        $discovered = ['kopling/core' => new Core()];

        foreach ($this->manifest->extensions() as $package => $extension) {
            $class = $extension['namespace'].'Extension';

            if (! class_exists($class) || ! is_subclass_of($class, AbstractExtension::class)) {
                continue;
            }

            $discovered[$package] = new $class();
        }

        return $this->extensions = Resolver::resolve($discovered);
    }

    /**
     * Directory-convention paths this package declares, keyed by kind. An extension gets these
     * registered purely by the directory existing -- no contract to implement, no code to write.
     *
     * Deliberately doesn't include routes/css/js: those always need a target Portal to attach
     * to (which prefix/middleware, which page's `<head>`), so a bare "the directory exists" rule
     * can't express them the way it can migrations/views/lang -- see `ExtendsPortals`/
     * `PortalExtension` instead.
     *
     * @return array<string, string>
     */
    public function conventions(string $package): array
    {
        $path = $this->path($package);

        if ($path === null) {
            return [];
        }

        $conventions = [];

        foreach (['migrations', 'views', 'lang'] as $kind) {
            $find = realpath($path.'/'.$kind);

            if ($find && is_dir($path.'/'.$kind)) {
                $conventions[$kind] = $find;
            }
        }

        return $conventions;
    }

    public function path(string $package): ?string
    {
        if ($package === 'kopling/core') {
            return __DIR__ . '/../../';
        }

        return $this->manifest->extensions()[$package]['path'] ?? null;
    }

    /**
     * The namespace an extension's views/translations/etc. get registered under. Includes
     * the vendor, not just the package name -- two different vendors can both publish an
     * extension called "example", and dropping the vendor would make their view/translation
     * namespaces collide (kopling/example and acme/example would both register "example::").
     */
    public function id(string $package): string
    {
        return str_replace('/', '-', $package);
    }

    /**
     * Storage requests declared by every extension, grouped by extension id the same way
     * `id()` namespaces views/translations -- so the admin storage-mapping screen can show
     * which extension owns each request instead of one anonymous, flattened list. Within
     * that, `StorageRequest::$id` is itself also prefixed the same way `permissions()`/
     * `portals()`/`ux()` prefix theirs, so two extensions declaring the same local id (e.g.
     * both wanting an "avatars" purpose) don't collide.
     *
     * @return array<string, array<StorageRequest>>
     */
    public function storageDrivers(): array
    {
        $requests = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof RequestsStorageDriver) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->storage())->ensure(StorageRequest::class);

            foreach ($declared as $request) {
                $request->id = $prefix.$request->id;
            }

            $requests[$this->id($package)] = $declared->all();
        }

        return $requests;
    }

    /**
     * Every admin-editable setting declared by every extension, grouped by owning extension id
     * -- same reasoning `storageDrivers()` groups by owner, so Admin's settings page can section
     * fields by which extension owns them. `Field::$id` is prefixed the same way
     * `permissions()`/`portals()` prefix theirs, so it doubles as a collision-safe persistence
     * key (see `Settings`).
     *
     * @return Collection<string, array<Field>>
     */
    public function adminSettings(): Collection
    {
        $settings = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasAdminSettings) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->adminSettings())->ensure(Field::class);

            foreach ($declared as $field) {
                $field->id = $prefix.$field->id;
            }

            $settings[$this->id($package)] = $declared->all();
        }

        return collect($settings);
    }

    /**
     * Every artisan command class declared by every extension. Unlike `permissions()`/
     * `portals()`/`ux()`, nothing here gets prefixed -- a command is referenced by its own
     * fully-qualified class name, already namespaced to the extension that declared it, so
     * there's no local id to collide with another extension's.
     *
     * `HasCommands::commands()` returns class-strings, not instantiated objects, so there's
     * no `Collection::ensure()` type to check against -- each entry is instead verified with
     * `is_subclass_of(..., Command::class)`, the equivalent guard against a `HasCommands`
     * implementor returning something that isn't actually an artisan command class.
     *
     * @return array<class-string>
     */
    public function commands(): array
    {
        $commands = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof HasCommands) {
                continue;
            }

            foreach ($extension->commands() as $command) {
                if (! is_string($command) || ! is_subclass_of($command, Command::class)) {
                    throw new \UnexpectedValueException(
                        sprintf('HasCommands::commands() should only include Command class-strings, but %s found.', get_debug_type($command))
                    );
                }

                $commands[] = $command;
            }
        }

        return $commands;
    }

    /**
     * Every permission declared by every extension, with `Permission::$id` already prefixed
     * with the owning extension's `id()` -- an author writes just the local part
     * ("manage-reactions"), never the prefix, so it can't drift or collide with another
     * extension's names.
     *
     * @return array<Permission>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasPermissions) {
                continue;
            }

            $declared = collect($extension->permissions())->ensure(Permission::class);

            foreach ($declared as $permission) {
                $permission->id = $this->id($package).'::'.$permission->id;

                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * Every Portal declared by every extension, with `Portal::$id` -- and `$permission`, when
     * set -- prefixed with the owning extension's `id()`, same authoring rule and collision-
     * safety reasoning as `permissions()`/`ux()`'s `$condition`. A future first-party
     * Moderation-portal extension (charter D29's own named proof case) slots into this same
     * loop for free.
     *
     * @return Collection<int, Portal>
     */
    public function portals(): Collection
    {
        $portals = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasPortals) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->portals())->ensure(Portal::class);

            foreach ($declared as $portal) {
                $portal->id = $prefix.$portal->id;

                if ($portal->permission !== null) {
                    $portal->permission = $prefix.$portal->permission;
                }

                $portals[] = $portal;
            }
        }

        return collect($portals)->keyBy('id');
    }

    /**
     * Every `PortalExtension` declared by every extension, grouped by the target Portal's
     * fully-qualified id -- the routes/css/js attachment counterpart to `portals()`'s identity
     * declarations. `PortalExtension::$portal` is a foreign reference (same convention as
     * `ux()`'s `after`/`before`), never prefixed here: targeting a Portal id that isn't actually
     * registered (a typo, or the declaring extension isn't installed) is a silent no-op, the
     * same graceful-degradation rule a dangling `ux()` anchor gets -- nothing to attach to, so
     * nothing happens, never an error.
     *
     * @return Collection<string, Collection<int, PortalExtension>>
     */
    public function portalExtensions(): Collection
    {
        $extensions = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ExtendsPortals) {
                continue;
            }

            $declared = collect($extension->extendsPortals())->ensure(PortalExtension::class);

            foreach ($declared as $portalExtension) {
                $extensions[$portalExtension->portal][] = $portalExtension;
            }
        }

        return collect($extensions)->map(fn (array $group) => collect($group));
    }

    /**
     * Flat, key-addressable registry of every css/js file any `PortalExtension` declared, keyed
     * by a stable hash of its own already-validated absolute path rather than anything derived
     * from a request -- `Http\Controllers\ExtensionAssetController` looks a request's `key` up
     * against this map and serves exactly the matched path, so a request can never walk this
     * into an arbitrary filesystem read the way a raw `{package}/{path}`-shaped route parameter
     * would invite.
     *
     * @return Collection<string, array{path: string, mime: string}>
     */
    public function extensionAssets(): Collection
    {
        $assets = [];

        foreach ($this->portalExtensions() as $group) {
            foreach ($group as $portalExtension) {
                foreach (['css' => 'text/css', 'js' => 'application/javascript'] as $kind => $mime) {
                    $path = $portalExtension->{$kind};

                    if ($path === null) {
                        continue;
                    }

                    $assets[static::assetKey($path)] = ['path' => $path, 'mime' => $mime];
                }
            }
        }

        return collect($assets);
    }

    /**
     * The URL a `<link>`/`<script>` tag should point at for a `PortalExtension`'s css/js file,
     * or null when it didn't declare one -- shared by `views/layouts/partials/head.blade.php`
     * and `extensionAssets()` so both always agree on the same key.
     */
    public static function assetUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return route('kopling-core::assets', ['key' => static::assetKey($path)]);
    }

    protected static function assetKey(string $path): string
    {
        return hash('xxh3', $path);
    }

    /**
     * Registers every model extension declared by every extension -- relations directly against
     * the target model via `Model::resolveRelationUsing()`, casts into `Database\Model`'s own
     * flat, class-keyed cast registry (`Database\Model::registerCasts()`, read by its
     * `getCasts()` override) -- a side effect, not an aggregation like `permissions()`/
     * `portals()`, so there's nothing meaningful to return beyond what `ListExtensionRegistrations`
     * -style introspection might want (mirrors `listeners()` otherwise). An `Extend\Model`
     * instance is scoped to exactly one model class by its own constructor, so there's exactly
     * one place "which model" is declared; where more than one extension targets the same
     * model, their `relations`/`casts` are combined, not replaced -- a relation-name collision
     * is last-registered-wins (`resolveRelationUsing()`'s own rule), a cast-key collision is
     * last-declared-wins among extensions, though core's own `$casts` always wins regardless of
     * declaration order (see `Database\Model::getCasts()`).
     *
     * `Collection::ensure(Extend\Model::class)` guards `ExtendsModels::models()` itself --
     * every item it returns must actually be a `Kopling\Core\Extend\Model` extender, not some
     * other value an implementor mistakenly returned.
     *
     * Cached on the instance (`Manager` is bound as a singleton) so the `resolveRelationUsing()`/
     * cast-registry side effects only ever run once, the same reasoning `extensions()` already
     * caches on.
     */
    public function models(): Collection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $declared = collect();

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ExtendsModels) {
                continue;
            }

            $declared->push(...$extension->models());
        }

        $declared->ensure(ExtendModel::class);

        $casts = [];

        $declared->each(function (ExtendModel $model) use (&$casts) {
            if (! class_exists($model->model)) {
                return;
            }

            /** @var class-string<EloquentModel> $class */
            $class = $model->model;

            foreach ($model->relations as $definition) {
                $class::resolveRelationUsing(
                    $definition['name'],
                    function (EloquentModel $instance) use ($definition) {
                        return $instance->{$definition['method']}(...$definition['constraint']);
                    }
                );
            }

            $casts[$model->model] = array_merge($casts[$model->model] ?? [], $model->casts);
        });

        DatabaseModel::registerCasts($casts);

        $this->models = $declared;

        return $declared;
    }

    /**
     * Every theme declared by every extension, keyed by owning extension id -- so an eventual
     * theme-picker UI can show which extension a theme came from, the same reasoning
     * `storageDrivers()` is keyed by owner instead of flattened. Unlike every other collector
     * here, keys inside each theme's array are never prefixed -- they're `Token::*->value`
     * strings naming a specific CSS custom property, a fixed catalog Manager itself checks
     * against, not a name space collision concern between extensions. An unrecognized key, or a
     * value that doesn't match that token's expected shape (`Token::matches()`), throws
     * immediately: a `ChangesTheme` implementor's own bug, not a foreign reference that might
     * legitimately not exist yet (contrast with `ux()`'s `after`/`before`).
     *
     * No selection between multiple installed themes exists yet -- every declared theme's
     * tokens simply get merged together, in `extensions()` order, last write wins on overlap.
     * Fine while at most one theme extension is ever installed; genuinely picking one active
     * theme among several is a real, not-yet-solved problem once a second one exists.
     *
     * @return Collection<string, array<string, string>>
     */
    public function themes(): Collection
    {
        $themes = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesTheme) {
                continue;
            }

            $declared = $extension->theme();

            foreach ($declared as $token => $value) {
                $case = Token::tryFrom($token);

                if ($case === null) {
                    throw new \InvalidArgumentException(
                        "[{$package}] declared an unrecognized theme token [{$token}]."
                    );
                }

                if (! $case->matches($value)) {
                    throw new \InvalidArgumentException(
                        "[{$package}]'s theme token [{$token}] has an invalid value [{$value}]."
                    );
                }
            }

            $themes[$this->id($package)] = $declared;
        }

        return collect($themes);
    }

    /**
     * Every installed theme as `[id => label]` for the theme switcher -- id the same key
     * `themes()` uses (so a picked id selects that theme's token set), label the extension's
     * own `name()`. Kept separate from `themes()` so the switcher can list themes without
     * paying to validate every token, and so a theme with no currently-valid tokens still
     * shows up as a choice.
     *
     * @return array<string, string>
     */
    public function themeChoices(): array
    {
        $choices = [];

        foreach ($this->extensions() as $package => $extension) {
            if ($extension instanceof ChangesTheme) {
                $choices[$this->id($package)] = $extension::name();
            }
        }

        return $choices;
    }

    /**
     * Every UxEntry declared by every extension (Core included, same as permissions()),
     * resolved down to what's actually registered once every extension's `Add`/`Replace`/
     * `Remove` operations have run, in `extensions()` order. `Add` entries get `UxEntry::$id`
     * -- and `$condition`, when it's a permission-id string, not a closure -- prefixed the
     * same way `permissions()` prefixes `Permission::$id`; `$slot`/`$after`/`$before` are left
     * untouched, since they're fully-qualified references the author writes out in full, not
     * private names Manager owns the prefixing of. `Replace`/`Remove` target another entry's
     * already fully-qualified id (same convention as `after`/`before`), so they're applied as
     * given, never prefixed. A `Replace`/`Remove` targeting an entry that isn't registered
     * (never was, or belongs to an extension processed later, or not installed at all) is a
     * no-op, same graceful-degradation rule `SlotResolver` applies to a dangling `after`/
     * `before` -- which also means an extension can only replace/remove something an
     * earlier-processed extension (or Core) already registered, not one processed after it.
     *
     * @return Collection<int, UxEntry>
     */
    public function ux(): Collection
    {
        $registry = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesUx) {
                continue;
            }

            $prefix = $this->id($package).'::';

            foreach ($extension->ux()->entries() as $entry) {
                match ($entry->action) {
                    UxAction::Add => $this->applyUxAdd($registry, $entry, $prefix),
                    UxAction::Replace => $this->applyUxReplace($registry, $entry),
                    UxAction::Remove => $this->applyUxRemove($registry, $entry),
                };
            }
        }

        return collect(array_values($registry));
    }

    /**
     * Registers every listener declared by every extension directly against the event
     * dispatcher -- a side effect, not an aggregation like `ux()`/`permissions()`, so there's
     * nothing to return.
     */
    public function listeners(): void
    {
        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ListensToEvents) {
                continue;
            }

            foreach ($extension->listen() as $event => $listener) {
                match (true) {
                    is_string($event) => $this->events->listen($event, $listener),
                    default => $this->events->subscribe($listener)
                };
            }
        }
    }

    /**
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxAdd(array &$registry, UxEntry $entry, string $prefix): void
    {
        $entry->id = $prefix.$entry->id;

        if (is_string($entry->condition)) {
            $entry->condition = $prefix.$entry->condition;
        }

        $registry[$entry->id] = $entry;
    }

    /**
     * Mutates the target in place rather than replacing it in the registry, so it keeps its
     * original position -- swapping what an entry looks like is never the same thing as
     * re-ordering it. Only `component`/`data` are always overwritten; `slot`/`after`/`before`/
     * `condition` are only overwritten if this `Replace` entry actually set them (chained after
     * `Ux::replace()`) -- left `null`, the target's original value stands.
     *
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxReplace(array &$registry, UxEntry $entry): void
    {
        $target = $registry[$entry->id] ?? null;

        if ($target === null) {
            return;
        }

        $target->component = $entry->component;
        $target->data = $entry->data;

        foreach (['slot', 'after', 'before', 'condition'] as $field) {
            if ($entry->{$field} !== null) {
                $target->{$field} = $entry->{$field};
            }
        }
    }

    /**
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxRemove(array &$registry, UxEntry $entry): void
    {
        unset($registry[$entry->id]);
    }
}
