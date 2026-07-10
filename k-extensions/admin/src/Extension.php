<?php

declare(strict_types=1);

namespace Kopling\Admin;

use Kopling\Core\Authorization\Permission;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;

/**
 * The Admin portal, split out of Core into its own extension -- Core keeps only the Community
 * portal, everything admin-facing (people/group management today, theme controls later) lives
 * here instead. Declared through the exact same contracts any other extension would use;
 * nothing about being "the admin panel" gets Core-only special treatment.
 *
 * TODO: this extension needs to be discovered/loaded early -- other extensions that want to
 * place their own settings/tools into this Portal's slots may want to anchor `after()`/
 * `before()` against entries this Portal registers, or `replace()`/`remove()` them, both of
 * which only work against an already-processed (earlier-loaded) extension (see
 * `Manager::ux()`). `Manager::extensions()` today only guarantees `Core` loads first;
 * Composer-discovered extensions (this one included) load in whatever order `installed.json`
 * happens to list them -- not yet controllable. A `composer.json`-declared load-priority
 * (e.g. an `extra.kopling.priority` int, `Manifest`/`Manager` sorting by it before instantiating)
 * is the likely shape, not decided or built yet.
 */
class Extension extends AbstractExtension implements CannotBeDisabled, HasPermissions, HasPortals
{
    public static function name(): string
    {
        return 'Admin';
    }

    public static function description(): string
    {
        return 'The Admin portal -- community-operator tools, gated behind their own permissions.';
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'access-admin',
                label: __('kopling-admin::permissions.access-admin.label'),
                description: __('kopling-admin::permissions.access-admin.description'),
            ),
        ];
    }

    /**
     * @return array<Portal>
     */
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
}
