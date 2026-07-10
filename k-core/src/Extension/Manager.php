<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Illuminate\Support\Collection;
use Kopling\Core\Authorization\Permission;
use Kopling\Core\Core;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Storage\StorageRequest;
use Kopling\Core\Ux\Theme\Token;
use Kopling\Core\Ux\UxAction;
use Kopling\Core\Ux\UxEntry;

class Manager
{
    /**
     * @var array<string, AbstractExtension>|null
     */
    protected ?array $extensions = null;

    public function __construct(protected Manifest $manifest)
    {
    }

    /**
     * `Core` (`'core'`) is always the first entry, guaranteed present -- it isn't Composer-
     * discovered the way the rest are (it declares no `"type": "kopling-extension"` package of
     * its own), it's the one thing `Manager` always loads regardless. Every other entry is a
     * genuinely discovered extension, keyed by Composer package name, instantiated once.
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

        $this->extensions = ['core' => new Core()];

        foreach ($this->manifest->extensions() as $package => $extension) {
            $class = $extension['namespace'].'Extension';

            if (! class_exists($class) || ! is_subclass_of($class, AbstractExtension::class)) {
                continue;
            }

            $this->extensions[$package] = new $class();
        }

        return $this->extensions;
    }

    /**
     * Directory-convention paths this package declares, keyed by kind. An extension gets
     * these registered (migrations, views, routes, lang) or made available (css, js) purely
     * by the directory existing -- no contract to implement, no code to write.
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

        foreach (['migrations', 'views', 'css', 'js', 'routes', 'lang'] as $kind) {
            if (is_dir($path.'/'.$kind)) {
                $conventions[$kind] = $path.'/'.$kind;
            }
        }

        return $conventions;
    }

    public function path(string $package): ?string
    {
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
            $declared = $extension->storage();

            foreach ($declared as $request) {
                $request->id = $prefix.$request->id;
            }

            $requests[$this->id($package)] = $declared;
        }

        return $requests;
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

            foreach ($extension->permissions() as $permission) {
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

            foreach ($extension->portals() as $portal) {
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
