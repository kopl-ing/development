<?php

declare(strict_types=1);

namespace Kopling\Admin;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\LoadOrder\Directive;
use Kopling\Core\Extension\LoadOrder\InfluencesLoadOrder;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Link;
use Kopling\Core\Ux\Portal\Navigation\Item;

/**
 * The Admin portal, split out of Core into its own extension -- Core keeps only the Community
 * portal, everything admin-facing (people/group management, settings, theme controls) lives
 * here instead. Declared through the exact same contracts any other extension would use;
 * nothing about being "the admin panel" gets Core-only special treatment.
 *
 * Now implements `InfluencesLoadOrder` per its own former TODO: anything implementing
 * `HasAdminSettings` loads after Admin, so its own `ChangesUx`/settings-page registration is
 * always in place first, without either side ever needing to know the other's Composer package
 * name (see `Extension\LoadOrder\Resolver`, and decisions.md, 2026-07-12).
 */
class Extension extends AbstractExtension implements CannotBeDisabled, ChangesUx, ExtendsPortals, HasPermissions, HasPortals, InfluencesLoadOrder
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
            new Permission(
                id: 'manage-settings',
                label: __('kopling-admin::permissions.manage-settings.label'),
                description: __('kopling-admin::permissions.manage-settings.description'),
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

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-admin::admin')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.settings'),
                'route' => 'kopling-admin::admin/settings',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('settings')
            ->when('manage-settings')
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.people'),
                'route' => 'kopling-admin::admin/people',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('people')
            ->when('kopling-core::manage-people')
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.groups'),
                'route' => 'kopling-admin::admin/groups',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('groups')
            ->when('kopling-core::manage-people')
            ->add(Link::class, [
                'label' => __('kopling-admin::messages.admin_panel'),
                'route' => 'kopling-admin::admin/index',
                'variant' => 'ghost',
            ])
            ->in('kopling-core::community.topbar')
            ->as('admin-link')
            ->when('access-admin')
            ->add(Link::class, [
                'label' => __('kopling-admin::messages.community'),
                'route' => 'kopling-core::community/community',
                'variant' => 'ghost',
            ])
            ->in('kopling-admin::admin.topbar')
            ->as('community-link');
    }

    /**
     * @return array<class-string, Directive>
     */
    public function loadOrderRules(): array
    {
        return [
            HasAdminSettings::class => Directive::After,
        ];
    }
}
