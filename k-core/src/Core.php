<?php

declare(strict_types=1);

namespace Kopling\Core;

use Kopling\Core\Authorization\Permission;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Ux\Ux;

/**
 * Core's own declarations, made through the same contracts any extension would implement --
 * `Kopling\Core\Extension\Manager` always includes this as its first entry, not Composer-
 * discovered like the rest (see `Manager::extensions()`). This replaces a previous
 * `CorePermissions` static registry that hand-wrote fully-prefixed ids ("core::manage-people")
 * as a special case; writing local ids here and letting `Manager` prefix them the same way it
 * prefixes any extension's removes that asymmetry -- one declaration mechanism, not two.
 */
class Core extends AbstractExtension implements ChangesUx, HasPermissions, HasPortals
{
    public static function name(): string
    {
        return 'Kopling Core';
    }

    public static function description(): string
    {
        return 'Authentication, permissions, extension loading, and the base UX component library.';
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'access-admin',
                label: 'Access admin panel',
                description: 'Allows access to the community admin panel.',
            ),
            new Permission(
                id: 'manage-people',
                label: 'Manage people',
                description: 'Create, edit, and remove people and groups.',
            ),
            new Permission(
                id: 'manage-theme',
                label: 'Manage theme',
                description: "Edit this community's theme design tokens.",
            ),
        ];
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(id: 'community', label: 'Community', path: '', layout: 'core::layouts.community'),
            new Portal(id: 'admin', label: 'Admin', path: 'admin', layout: 'core::layouts.admin', permission: 'access-admin'),
        ];
    }

    /**
     * Replaces `layouts/admin.blade.php`'s previous hardcoded `@can('core::manage-theme')`
     * nav link -- the same entry, now declared through the mechanism any extension uses too.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add('k::portal.navigation.item', ['label' => 'Theme', 'route' => 'core::admin.theme'])
            ->in('core::side-navigation')
            ->as('theme')
            ->when('manage-theme');
    }
}
