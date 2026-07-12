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
 * TODO: load order is now controllable (`Extension\LoadOrder\HasLoadOrder`/
 * `InfluencesLoadOrder`, resolved by `Extension\LoadOrder\Resolver` inside
 * `Manager::extensions()`), but Admin doesn't need either yet -- there's no `HasSettings`-style
 * contract yet for other extensions to place their own settings/tools into this Portal's slots
 * against, so nothing here is order-sensitive today. Once that contract exists, Admin should
 * implement `InfluencesLoadOrder` and return `[HasSettings::class => Directive::After]` from
 * `loadOrderRules()`, so anything implementing it loads after Admin without either side ever
 * needing to know the other's Composer package name.
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
