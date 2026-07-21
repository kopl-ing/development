<?php

declare(strict_types=1);

namespace Kopling\StyleGuide;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Community\Navigation;
use Kopling\Core\Ux\Community\ThemeSwitcher;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;

/**
 * A design-system showcase, not a "real" feature -- its own Portal (gated by
 * `access-style-guide`) rather than a page bolted onto Community/Admin, so it can be disabled
 * per-install like anything else without touching either. Deliberately not `CannotBeDisabled`:
 * unlike Core, there's nothing structural that breaks if a host disables it. See
 * `tests/Feature/StyleGuide/ComponentCoverageTest.php` for the mechanism that keeps this
 * extension's own showcase honest as core's `<x-k::*>` inventory grows -- the enforcement, not
 * this class, is the point.
 */
class Extension extends AbstractExtension implements HasPortals, ExtendsPortals, HasPermissions, HasIcons, ChangesUx
{
    public static function name(): string
    {
        return 'Style Guide';
    }

    public static function description(): string
    {
        return "A living showcase of every core <x-k::*> component, kept in sync by a coverage test rather than hand-maintained docs.";
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'style-guide',
                label: 'Style Guide',
                path: 'style-guide',
                layout: 'kopling-style-guide::layouts.style-guide',
                permission: 'access-style-guide',
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
                id: 'access-style-guide',
                label: __('kopling-style-guide::permissions.access-style-guide.label'),
                description: __('kopling-style-guide::permissions.access-style-guide.description'),
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-style-guide::style-guide')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }

    /**
     * A paintbrush for the style guide's own user-menu entry -- same `HasIcons` mechanism as
     * `Admin::icons()`'s `admin-panel`, so an icon pack or theme can override it later.
     *
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'style-guide', label: __('kopling-style-guide::messages.title'), default: 'fas-brush'),
        ];
    }

    /**
     * A live, working theme toggle in this portal's own topbar slot (see
     * `layouts/style-guide.blade.php`) -- not just the passive demo in the Actions section, so
     * every real installed theme can actually be flipped through while browsing the style guide
     * itself. Same component, same global `kopling_theme` cookie the real Community topbar uses.
     *
     * `user-menu` in this same topbar slot -- the exact same avatar dropdown Community's own
     * chrome renders (`UserMenu::defaults()`'s registration is a separate slot entry; this is
     * this portal's own), so a signed-in person browsing the style guide has the same way back to
     * Community and, if permitted, Admin -- rather than only a browser-back button. Its own
     * `UserMenu::SLOT` already carries `community-link` (Core's own default) and Admin's
     * `admin-link`; nothing style-guide-specific needs adding there beyond `style-guide-link`
     * itself just below, which already targets that same slot.
     *
     * `style-guide-link` in the community's own user menu (`UserMenu::SLOT`), gated by
     * `access-style-guide` -- same shape as Admin's own `admin-link` entry there, just without
     * `->first()`: nothing here needs to lead the menu the way "Admin panel" does.
     *
     * `navigation-panel` reuses `Community\Navigation` (its own `$data['slot']` override pointed
     * at this portal's own new `style-guide.navigation` slot) as this portal's one entry in
     * `Chrome`'s generic `style-guide.sidebar-panel` slot -- the same shared chrome layout
     * Community/Admin use (see `layouts/style-guide.blade.php`). The five section anchors below
     * it were previously hardcoded `<li><a href="#tokens">` markup directly in that layout file;
     * now real `Ux::add()` entries (the `kopling-style-guide::nav-anchor` anonymous component,
     * `views/components/nav-anchor.blade.php`) into that same slot, same reasoning as everything
     * else in this pass -- one genuinely shared mechanism, not a page-specific special case.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add(ThemeSwitcher::class)
            ->in('kopling-style-guide::style-guide.topbar')
            ->as('theme-switcher')
            ->add(UserMenu::class)
            ->in('kopling-style-guide::style-guide.topbar')
            ->as('user-menu')
            ->after('theme-switcher')
            ->add(Item::class, [
                'label' => __('kopling-style-guide::messages.title'),
                'route' => 'kopling-style-guide::style-guide/index',
                'icon' => 'kopling-style-guide::style-guide',
                'hideOnPortal' => 'kopling-style-guide::style-guide',
            ])
            ->in(UserMenu::SLOT)
            ->as('style-guide-link')
            ->when('access-style-guide')
            ->add(Navigation::class, ['slot' => 'kopling-style-guide::style-guide.navigation'])
            ->in('kopling-style-guide::style-guide.sidebar-panel')
            ->as('navigation-panel')
            ->add('kopling-style-guide::nav-anchor', ['href' => '#tokens', 'label' => __('kopling-style-guide::messages.tokens')])
            ->in('kopling-style-guide::style-guide.navigation')
            ->as('tokens')
            ->add('kopling-style-guide::nav-anchor', ['href' => '#forms', 'label' => __('kopling-style-guide::messages.forms')])
            ->in('kopling-style-guide::style-guide.navigation')
            ->as('forms')
            ->after('tokens')
            ->add('kopling-style-guide::nav-anchor', ['href' => '#actions', 'label' => __('kopling-style-guide::messages.actions')])
            ->in('kopling-style-guide::style-guide.navigation')
            ->as('actions')
            ->after('forms')
            ->add('kopling-style-guide::nav-anchor', ['href' => '#editor', 'label' => __('kopling-style-guide::messages.editor')])
            ->in('kopling-style-guide::style-guide.navigation')
            ->as('editor')
            ->after('actions')
            ->add('kopling-style-guide::nav-anchor', ['href' => '#card', 'label' => __('kopling-style-guide::messages.card')])
            ->in('kopling-style-guide::style-guide.navigation')
            ->as('card')
            ->after('editor')
            ->add('kopling-style-guide::nav-anchor', ['href' => '#compose', 'label' => __('kopling-style-guide::messages.compose')])
            ->in('kopling-style-guide::style-guide.navigation')
            ->as('compose')
            ->after('card');
    }
}
