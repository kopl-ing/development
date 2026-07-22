<?php

declare(strict_types=1);

namespace Kopling\Pages;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;

/**
 * An admin-authored pages CMS -- its own public Portal (ungated: `permission: null`, unlike
 * Admin/Style Guide) for rendering published pages, plus an admin CRUD screen attached into the
 * *existing* Admin portal rather than a bolted-on page inside this one -- same "attach into an
 * existing Portal you don't own" shape Tags/Reactions/Poll already use for their own admin
 * screens (`extendsPortals()` targets `kopling-admin::admin` for that half, `kopling-pages::pages`
 * for the public half).
 *
 * `UserMenu` on this Portal's own topbar renders correctly for a signed-in person (the same
 * shared component/slot Admin and Style Guide already reuse), but there is currently no
 * mechanism for a *guest* to see login/register links here -- `auth-email-password` hardcodes
 * its `login-link`/`register-link` registration to `kopling-core::community.topbar` specifically,
 * with no portal-scoped slot generalization to target instead (see roadmap.md, "Ux /
 * extensibility"). Known gap for a Portal meant to be reached by anonymous visitors, not solved
 * here -- solving it generally is a bigger change than this extension's own scope.
 */
class Extension extends AbstractExtension implements HasPortals, ExtendsPortals, HasPermissions, ChangesUx
{
    public static function name(): string
    {
        return 'Pages';
    }

    public static function description(): string
    {
        return 'An admin-authored pages CMS -- build landing/marketing pages from admin-defined, reusable Blade section templates.';
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'pages',
                label: 'Pages',
                path: 'pages',
                layout: 'kopling-pages::layouts.pages',
            ),
        ];
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'manage-pages',
                label: __('kopling-pages::permissions.manage-pages.label'),
                description: __('kopling-pages::permissions.manage-pages.description'),
            ),
            // Deliberately its own permission, not folded into "manage-pages" -- a section
            // template's `blade_source` is compiled via Blade::render() at display time, full
            // directive support included, so authoring one is writing server-executing PHP, not
            // page content. Someone trusted to write/publish pages isn't automatically trusted
            // to write server-executing templates.
            new Permission(
                id: 'manage-page-templates',
                label: __('kopling-pages::permissions.manage-page-templates.label'),
                description: __('kopling-pages::permissions.manage-page-templates.description'),
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-pages::pages')
                ->routes(__DIR__.'/../routes/web.php'),
            new PortalExtension('kopling-admin::admin')
                ->routes(__DIR__.'/../routes/admin.php'),
        ];
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(Item::class, [
                'label' => __('kopling-pages::messages.pages'),
                'route' => 'kopling-admin::admin/pages',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('pages')
            ->when('manage-pages')
            ->add(Item::class, [
                'label' => __('kopling-pages::messages.section_templates'),
                'route' => 'kopling-admin::admin/section-templates',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('section-templates')
            ->when('manage-page-templates')
            ->add(UserMenu::class)
            ->in('kopling-pages::pages.topbar')
            ->as('user-menu');
    }
}
