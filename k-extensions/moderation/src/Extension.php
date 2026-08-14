<?php

declare(strict_types=1);

namespace Kopling\Moderation;

use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\ModerationTarget;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\RegistersModerationTargets;
use Kopling\Core\People\Person;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Card\Control;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\Moderation\Command\SeedModeratorsCommand;
use Kopling\Moderation\Ux\DeleteControlEntry;
use Kopling\Moderation\Ux\HideControlEntry;
use Kopling\Moderation\Ux\QueueNav;
use Kopling\Moderation\Ux\ReportControlEntry;

class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasCommands, HasIcons, HasPermissions, HasPortals, RegistersModerationTargets
{
    public static function name(): string
    {
        return 'Moderation';
    }

    public static function description(): string
    {
        return 'Report, review, and act on flagged Moments, Replies, and People -- extensible so any extension can register its own flaggable model.';
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'moderate',
                label: __('kopling-moderation::permissions.moderate.label'),
                description: __('kopling-moderation::permissions.moderate.description'),
            ),
        ];
    }

    /**
     * `label` is a plain string, not `__()` -- `Manager::portals()` is resolved eagerly during
     * `ServiceProvider::boot()`, before that loop's own lang-file registration runs, so a
     * translation call here would resolve to nothing and render its own raw key. Every existing
     * Portal declaration (`Core`, `admin`, `docs`, `style-guide`) follows the same convention for
     * the same reason -- confirmed the hard way, not a style choice.
     *
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'moderation',
                label: 'Moderation',
                path: 'moderation',
                layout: 'kopling-moderation::layouts.moderation',
                permission: 'moderate',
            ),
        ];
    }

    /**
     * Report/hide/unhide are triggered from Community pages (a Moment/Reply's own card), so
     * those routes attach there -- the same `_xhr/{extension-id}/...` convention pin/reactions
     * already use, `auth` middleware only, `authorize()` enforced inside each controller. The
     * queue itself lives under this extension's own Moderation portal, which already gates its
     * whole route group behind `moderate`.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            (new PortalExtension('kopling-core::community'))
                ->routes(__DIR__.'/../routes/community.php'),
            (new PortalExtension('kopling-moderation::moderation'))
                ->routes(__DIR__.'/../routes/moderation.php'),
        ];
    }

    /**
     * The three built-in flaggable types. `Reply` is `class_exists`-guarded, the same
     * soft-dependency convention `reactions` already uses -- moderation works with just Moment
     * when discussions isn't installed. `Person` declares no preview action beyond Report --
     * hide/delete never apply to a Person (see `ModerationTarget::$softDeletable`, computed from
     * actual trait usage, never author-declared).
     *
     * @return array<ModerationTarget>
     */
    public function moderationTargets(): array
    {
        $targets = [
            new ModerationTarget(
                model: Moment::class,
                label: __('kopling-moderation::messages.targets.moment'),
                preview: 'kopling-moderation::preview.moment',
            ),
            new ModerationTarget(
                model: Person::class,
                label: __('kopling-moderation::messages.targets.person'),
                preview: 'kopling-moderation::preview.person',
            ),
        ];

        if (class_exists(\Kopling\Discussions\Reply::class)) {
            $targets[] = new ModerationTarget(
                model: \Kopling\Discussions\Reply::class,
                label: __('kopling-moderation::messages.targets.reply'),
                preview: 'kopling-moderation::preview.reply',
            );
        }

        return $targets;
    }

    /**
     * Report + Hide/Unhide + Delete control-slot entries, registered into both a Moment's own
     * `Card\Control::SLOT` and a Reply's `kopling-discussions::reply.control` -- a plain string
     * literal, never `Reply::CONTROL_SLOT` (no `use Kopling\Discussions\Reply` anywhere in this
     * file), so this registration costs nothing and renders nothing if discussions isn't
     * installed, the exact convention `reactions` already established for its own footer entries.
     * Hide/Delete are gated `moderate`; Report isn't -- reporting needs no permission by design,
     * only a signed-in session (enforced by the route's own `auth` middleware).
     *
     * `user-menu` in this portal's own topbar slot is the same avatar dropdown Community/Admin
     * render -- same registration Admin's own `Extension::ux()` uses for its own topbar slot.
     * `queue-link` uses `UserMenu::PRIORITY_TOP`, same as Admin's/Style Guide's own Portal links,
     * so the three lead the dropdown regardless of extension load order.
     */
    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(ReportControlEntry::class)
            ->in(Control::SLOT)
            ->as('report-control-entry')
            ->add(HideControlEntry::class)
            ->in(Control::SLOT)
            ->as('hide-control-entry')
            ->when('moderate')
            ->after('kopling-moderation::report-control-entry')
            ->add(DeleteControlEntry::class)
            ->in(Control::SLOT)
            ->as('delete-control-entry')
            ->when('moderate')
            ->after('kopling-moderation::hide-control-entry')
            ->add(ReportControlEntry::class)
            ->in('kopling-discussions::reply.control')
            ->as('reply-report-control-entry')
            ->add(HideControlEntry::class)
            ->in('kopling-discussions::reply.control')
            ->as('reply-hide-control-entry')
            ->when('moderate')
            ->after('kopling-moderation::reply-report-control-entry')
            ->add(DeleteControlEntry::class)
            ->in('kopling-discussions::reply.control')
            ->as('reply-delete-control-entry')
            ->when('moderate')
            ->after('kopling-moderation::reply-hide-control-entry')
            ->add(Item::class, [
                'label' => __('kopling-moderation::messages.portal_label'),
                'route' => 'kopling-moderation::moderation/queue.index',
                'icon' => 'kopling-moderation::moderation',
                'hideOnPortal' => 'kopling-moderation::moderation',
            ])
            ->in(UserMenu::SLOT)
            ->as('queue-link')
            ->when('moderate')
            ->priority(UserMenu::PRIORITY_TOP)
            ->add(QueueNav::class)
            ->in('kopling-moderation::moderation.sidebar-panel')
            ->as('queue-nav')
            ->add(UserMenu::class)
            ->in('kopling-moderation::moderation.topbar')
            ->as('user-menu');
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedModeratorsCommand::class];
    }

    public function icons(): array
    {
        return [
            new Icon(id: 'moderation', label: __('kopling-moderation::messages.portal_label'), default: 'fas-handcuffs'),
        ];
    }
}
