<?php

declare(strict_types=1);

namespace Kopling\Admin;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\LoadOrder\Directive;
use Kopling\Core\Extension\LoadOrder\InfluencesLoadOrder;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Community\Navigation;
use Kopling\Core\Ux\Community\UserMenu;
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
class Extension extends AbstractExtension implements CannotBeDisabled, ChangesUx, ExtendsPortals, HasIcons, HasPermissions, HasPortals, InfluencesLoadOrder
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
     * A safety helmet for the admin panel's own user-menu entry -- same `HasIcons` mechanism as
     * any other semantic icon (see `Core::icons()`), so a `ChangesIcons` icon pack can override
     * it, or a theme's own choice can replace it, without touching `ux()`'s `Item` registration
     * itself.
     *
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'admin-panel', label: __('kopling-admin::messages.admin_panel'), default: 'fas-helmet-safety'),
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

    /**
     * `user-menu` in this portal's own topbar slot is the exact same avatar dropdown Community's
     * chrome and the style guide's own layout render -- was a bare `Link` back to Community until
     * now, replaced so a person browsing Admin gets the same consistent way back/around instead
     * of a single hardcoded link. `admin-link` (this portal's own entry inside that dropdown, on
     * every page that renders it) hides itself via `hideOnPortal` while already on the Admin
     * portal -- no point linking to exactly where the viewer already is.
     *
     * `navigation-panel` reuses `Community\Navigation` (its own `$data['slot']` override pointed
     * at this portal's existing `admin.navigation` slot -- `settings`/`people`/`groups` below are
     * unchanged) as this portal's one entry in `Chrome`'s generic `admin.sidebar-panel` slot --
     * the same shared chrome layout Community/Style Guide use (see `layouts/admin.blade.php`).
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add(Navigation::class, ['slot' => 'kopling-admin::admin.navigation'])
            ->in('kopling-admin::admin.sidebar-panel')
            ->as('navigation-panel')
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.settings'),
                'route' => 'kopling-admin::admin/settings',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('settings')
            ->when('manage-settings')
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.drives'),
                'route' => 'kopling-admin::admin/drives',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('drives')
            ->when('manage-settings')
            ->after('settings')
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.storage'),
                'route' => 'kopling-admin::admin/storage',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('storage')
            ->when('manage-settings')
            ->after('drives')
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.portals'),
                'route' => 'kopling-admin::admin/portals',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('portals')
            ->when('manage-settings')
            ->after('storage')
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
            ->add(Item::class, [
                'label' => __('kopling-admin::messages.admin_panel'),
                'route' => 'kopling-admin::admin/index',
                'icon' => 'kopling-admin::admin-panel',
                'hideOnPortal' => 'kopling-admin::admin',
            ])
            ->in(UserMenu::SLOT)
            ->as('admin-link')
            ->when('access-admin')
            ->first()
            ->add(UserMenu::class)
            ->in('kopling-admin::admin.topbar')
            ->as('user-menu');
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
