<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Illuminate\Support\Collection;
use Kopling\Core\Authorization\Permission;
use Kopling\Core\Core;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Storage\StorageRequest;
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
     * Every Portal declared by every extension, with `Portal::$id` already prefixed with the
     * owning extension's `id()` -- same authoring rule, same collision-safety reasoning as
     * `permissions()`. `Core` is the sole implementor today (`core::community`,
     * `core::admin`); a future first-party Moderation-portal extension (charter D29's own named
     * proof case) slots into this same loop for free.
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

            foreach ($extension->portals() as $portal) {
                $portal->id = $this->id($package).'::'.$portal->id;

                $portals[] = $portal;
            }
        }

        return collect($portals)->keyBy('id');
    }

    /**
     * Every UxEntry declared by every extension (Core included, same as permissions()), with
     * `UxEntry::$id` -- and `$condition` when it's a permission-id string, not a closure --
     * prefixed the same way `permissions()` prefixes `Permission::$id`. `$slot`/`$after`/
     * `$before` are left untouched: they're fully-qualified references the author writes out
     * in full, not private names Manager owns the prefixing of.
     *
     * @return Collection<int, UxEntry>
     */
    public function ux(): Collection
    {
        $entries = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesUx) {
                continue;
            }

            $prefix = $this->id($package).'::';

            foreach ($extension->ux()->entries() as $entry) {
                $entry->id = $prefix.$entry->id;

                if (is_string($entry->condition)) {
                    $entry->condition = $prefix.$entry->condition;
                }

                $entries[] = $entry;
            }
        }

        return collect($entries);
    }
}
