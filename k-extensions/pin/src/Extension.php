<?php

declare(strict_types=1);

namespace Kopling\Pin;

use Kopling\Core\Content\Event\QueryingMoments;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\ListensToEvents;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Card\Control;
use Kopling\Core\Ux\Card\Event\RenderingCard;
use Kopling\Pin\Listeners\DecoratePinnedCard;
use Kopling\Pin\Listeners\ExcludeVisiblePinnedMoments;
use Kopling\Pin\Ux\EditPinControlEntry;
use Kopling\Pin\Ux\PinControlEntry;
use Kopling\Pin\Ux\PinnedSection;

class Extension extends AbstractExtension implements ChangesUx, ExtendsModels, ExtendsPortals, HasPermissions, ListensToEvents
{
    public static function name(): string
    {
        return 'Pin';
    }

    public static function description(): string
    {
        return 'Pin a Moment to the top of the community feed, with a reason, optional scheduling window, and optional Groups targeting.';
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'pin-moments',
                label: __('kopling-pin::permissions.pin-moments.label'),
                description: __('kopling-pin::permissions.pin-moments.description'),
            ),
        ];
    }

    /**
     * Attaches a `pin` relation to core's `Moment`, eager-loaded so the main feed's paginator
     * batch-loads it for every card at once -- the same anti-N+1 reasoning `reactions` already
     * established for its own relation.
     *
     * @return array<Model>
     */
    public function models(): array
    {
        return [
            new Model(Moment::class)
                ->relation((new Relation)->hasOne('pin', Pin::class)->eagerLoad()),
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    public function listen(): array
    {
        return [
            QueryingMoments::class => ExcludeVisiblePinnedMoments::class,
            RenderingCard::class => DecoratePinnedCard::class,
        ];
    }

    /**
     * `EditPinControlEntry`/`PinControlEntry` are the pin/edit-pin/unpin actions in the card's
     * own "⋮" menu, gated behind `pin-moments` -- two entries, not one dual-state component, so
     * each renders its own `<li>` (see `Card\Control`'s own view) instead of both actions
     * sharing one wrapper the way this used to render. `->after()` keeps "Edit pin" ahead of
     * "Unpin", the same visual order the old combined entry produced. `PinnedSection` fills the
     * community layout's already-existing `content-top` slot with whatever's currently pinned
     * and visible -- no permission gate on the slot entry itself, since visibility there is a
     * per-pin/per-viewer question `Pin::visibleFor()` already answers, not a capability check.
     */
    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(EditPinControlEntry::class)
            ->in(Control::SLOT)
            ->as('edit-pin-control-entry')
            ->when('pin-moments')
            ->add(PinControlEntry::class)
            ->in(Control::SLOT)
            ->as('pin-control-entry')
            ->when('pin-moments')
            ->after('kopling-pin::edit-pin-control-entry')
            ->add(PinnedSection::class)
            ->in('kopling-core::community.content-top')
            ->as('pinned-section');
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }
}
