<?php

declare(strict_types=1);

namespace Kopling\Tags;

use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Tags\Command\SeedDemoTagsCommand;

class Extension extends AbstractExtension implements ChangesUx, ExtendsModels, ExtendsPortals, HasCommands
{
    public static function name(): string
    {
        return 'Tags';
    }

    public static function description(): string
    {
        return 'Categorise moments with tags and browse everything under one.';
    }

    /**
     * A tag badge row at the top of each card's body (before core's own `content`), reading
     * the moment from `$context->subject`. Registered by anonymous-component tag, the same
     * way the reactions extension registers into the footer.
     */
    public function ux(): Ux
    {
        // `before` takes the anchor's fully-qualified id -- core's Content entry resolves to
        // `core::content` (see Card\Body::defaults), so the tag row sits above the title/body.
        return Ux::make()
            ->add('kopling-tags::tags')
            ->in('kopling-core::card.body')
            ->as('tags')
            ->before('kopling-core::content');
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedDemoTagsCommand::class];
    }

    /**
     * Adds a `tags` relation to core's `Moment` (the pivot side, declared here so core's model
     * stays untouched), eager-loaded so a feed's tag rows read one batch-loaded relation per
     * moment instead of a `whereHas` per card -- the O(cards) cost issue #4 measured.
     * `Tag::forMoment` reads this relation.
     *
     * @return array<Model>
     */
    public function models(): array
    {
        return [
            (new Model(Moment::class))
                ->relation((new Relation)->belongsToMany('tags', Tag::class, 'moment_tag')->eagerLoad()),
        ];
    }

    /**
     * The public tag page (/tag/{slug}) attaches to Community — it reuses the base portal
     * shell + core's card component. Rides the portal's own group (web + prefix + name); route
     * name is now kopling-core::community/tags.show.
     *
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
