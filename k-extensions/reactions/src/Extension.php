<?php

declare(strict_types=1);

namespace Kopling\Reactions;

use Kopling\Core\Content\Event\QueryingMoments;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\ListensToEvents;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Reactions\Command\SeedDemoReactionsCommand;
use Kopling\Reactions\Listeners\SortMomentsByVotes;

class Extension extends AbstractExtension implements ChangesUx, ExtendsModels, ExtendsPortals, HasCommands, ListensToEvents
{
    public static function name(): string
    {
        return 'Reactions';
    }

    public static function description(): string
    {
        return 'Emoji and worded reactions for moments.';
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedDemoReactionsCommand::class];
    }

    /**
     * @return array<class-string, class-string>
     */
    public function listen(): array
    {
        return [
            QueryingMoments::class => SortMomentsByVotes::class,
        ];
    }

    /**
     * Adds a `reactions` relation to core's `Moment` (from the extension side, never touching
     * the core model), eager-loaded so a feed's rails and "Latest reactions" strips read one
     * batch-loaded relation per moment instead of each firing its own per-card queries -- the
     * O(cards) cost issue #4 measured. `Reaction::state`/`latestWorded` read this relation;
     * `$with = ['person']` on Reaction nests the authors into the same batch.
     *
     * @return array<Model>
     */
    public function models(): array
    {
        return [
            (new Model(Moment::class))
                ->relation((new Relation)->hasMany('reactions', Reaction::class)->eagerLoad()),
        ];
    }

    /**
     * Fills the `core::card.footer` slot that `Card\Footer` deliberately leaves empty for a
     * real reactions feature, with two entries: the emoji `rail` (the calm aggregate) and the
     * `words` strip ("Latest reactions") after it. Both are registered by their anonymous
     * component tag, not a class -- extensions get an auto view namespace but not a
     * class-component namespace, so `ComponentTag` passes the tag through untouched and the
     * footer renders each via `<x-dynamic-component>`.
     *
     * The `modal` (the picker) goes into the chrome's page-level `community.composer` slot, not
     * the per-card footer -- it's one modal for the whole page, opened against whichever card's
     * "+" the viewer clicked (see modal.blade + js/app.js).
     *
     * `vote` sits before `rail` -- the tag-gated vote buttons (self-hiding when a moment's tags
     * configure none) sit "sticky above" the generic emoji rail, per the roadmap's own wording.
     * `sort-toggle` fills Community's `content-top` slot (the same one Pin's own pinned section
     * uses) with a "Latest / Top" link pair, self-hiding when no tag configures upvoting yet.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-reactions::vote')
            ->in('kopling-core::card.footer')
            ->as('vote')
            ->before('kopling-reactions::rail')
            ->add('kopling-reactions::rail')
            ->in('kopling-core::card.footer')
            ->as('rail')
            ->add('kopling-reactions::words')
            ->in('kopling-core::card.footer')
            ->as('words')
            ->after('kopling-reactions::rail')
            ->add('kopling-reactions::modal')
            ->in('kopling-core::community.composer')
            ->as('modal')
            ->add('kopling-reactions::sort-toggle')
            ->in('kopling-core::community.content-top')
            ->as('sort-toggle');
    }

    /**
     * The toggle/word routes attach to Community — the only portal a card feed renders in.
     * They ride the portal's own Route::group() (web + prefix + name) and keep their `auth`
     * gate; route names are now kopling-core::community/reactions.toggle|word. css/app.css (the
     * rail/chip/modal styling) and js/app.js (the post-swap flash on vote.blade/rail.blade's own
     * buttons) are linked onto Community pages via the head-assets outlet -- the picker modal
     * itself stays event-driven local Alpine, no js of its own needed there.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php')
                ->css(__DIR__.'/../css/app.css')
                ->js(__DIR__.'/../js/app.js'),
        ];
    }
}
