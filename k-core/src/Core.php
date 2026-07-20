<?php

declare(strict_types=1);

namespace Kopling\Core;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Contract\ChangesEditor;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Card\Badges;
use Kopling\Core\Ux\Card\Body;
use Kopling\Core\Ux\Card\Control;
use Kopling\Core\Ux\Card\Footer;
use Kopling\Core\Ux\Card\Top;
use Kopling\Core\Ux\Community\Chrome;
use Kopling\Core\Ux\Community\Navigation;
use Kopling\Core\Ux\Community\ThemeSwitcher;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Editor;
use Kopling\Core\Ux\Editor\EditorNode;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\Input;
use Kopling\Core\Ux\Form\TextArea;

/**
 * Core's own declarations, made through the same contracts any extension would implement --
 * `Kopling\Core\Extension\Manager` always includes this as its first entry, not Composer-
 * discovered like the rest (see `Manager::extensions()`). This replaces a previous
 * `CorePermissions` static registry that hand-wrote fully-prefixed ids ("kopling-core::manage-people")
 * as a special case; writing local ids here and letting `Manager` prefix them the same way it
 * prefixes any extension's removes that asymmetry -- one declaration mechanism, not two.
 */
class Core extends AbstractExtension implements CannotBeDisabled, ChangesEditor, ChangesUx, ExtendsPortals, HasAdminSettings, HasIcons, HasPermissions, HasPortals
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
                id: 'access-community',
                label: 'Access community',
                description: 'Access the community portal.',
                allowsGuests: true,
            ),
            new Permission(
                id: 'manage-permissions',
                label: 'Manage permissions',
                description: 'Assign and modify permissions.',
            ),
            new Permission(
                id: 'manage-people',
                label: 'Manage people',
                description: 'Create, edit, and remove people and groups.',
            ),
            new Permission(
                id: 'guest',
                label: 'Guest',
                description: 'Granted only to a signed-out visitor -- for UI that should show only when signed out (e.g. sign-in links).',
                allowsGuests: true,
            ),
        ];
    }

    /**
     * The Community portal's own identity -- all three optional, all read directly via
     * `Settings::get()` wherever they're actually used (`Community\Chrome` for name/logo,
     * `layouts/partials/head.blade.php` for the meta description) rather than threaded through
     * here, the same "declare the field, read it where it's needed" split every other
     * `HasAdminSettings` field already follows. Community-specific by name (not a generic "site
     * name"/"site logo"): `Chrome` only ever substitutes these for `$portal->label` when
     * `$portalId` is actually `kopling-core::community` -- Admin and Style Guide keep showing
     * their own real portal label regardless of what's configured here.
     *
     * @return array<Field>
     */
    public function adminSettings(): array
    {
        return [
            new Field(
                id: 'community-name',
                label: 'Community name',
                component: Input::class,
                description: 'Optional -- shown in the topbar instead of "Community" (the '.
                    'portal\'s own default label).',
            ),
            new Field(
                id: 'community-logo',
                label: 'Community logo URL',
                component: Input::class,
                description: 'Optional -- shown in the topbar instead of the community name '.
                    'above, once set.',
            ),
            new Field(
                id: 'community-description',
                label: 'Community description',
                component: TextArea::class,
                description: 'Optional -- used as the site\'s <meta name="description"> tag.',
            ),
        ];
    }

    /**
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'home', label: 'Home', default: 'fas-house'),
            new Icon(id: 'theme-switch', label: 'Theme switch', default: 'fas-palette'),
            new Icon(id: 'post-actions', label: 'Post actions', default: 'fas-ellipsis-vertical'),
        ];
    }

    /**
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'community',
                label: 'Community',
                path: '',
                layout: 'kopling-core::layouts.community',
            ),
        ];
    }

    /**
     * v1's default enabled set -- a lean, essential formatting baseline, not an exhaustive tour
     * of everything TipTap can do: `Strike`/`Underline`/`TaskList`/`HorizontalRule` are
     * deliberately left off by default so `ChangesEditor` has real room for an extension to
     * matter, rather than Core exhausting the whole catalog itself and leaving nothing to
     * extend. Declared through the same `ChangesEditor` contract any extension would use, not
     * hardcoded elsewhere, same "core's own defaults go through the same contract" rule
     * `icons()`/`permissions()` already establish.
     *
     * @return array<EditorNode>
     */
    public function editor(): array
    {
        return [
            EditorNode::Heading,
            EditorNode::Bold,
            EditorNode::Italic,
            EditorNode::Code,
            EditorNode::CodeBlock,
            EditorNode::Blockquote,
            EditorNode::BulletList,
            EditorNode::OrderedList,
            EditorNode::HardBreak,
            EditorNode::Link,
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/community.php'),
        ];
    }

    /**
     * A thin composition point, not a dumping ground -- each component declares its own
     * defaults on itself (see Top/Footer/Body's own `defaults()`); this just calls them.
     */
    public function ux(): Ux
    {
        $ux = Ux::make();

        Top::defaults($ux);
        Badges::defaults($ux);
        Footer::defaults($ux);
        Control::defaults($ux);
        Body::defaults($ux);
        Navigation::defaults($ux);
        ThemeSwitcher::defaults($ux);
        UserMenu::defaults($ux);
        Chrome::defaults($ux);
        Editor::defaults($ux);

        return $ux;
    }
}
