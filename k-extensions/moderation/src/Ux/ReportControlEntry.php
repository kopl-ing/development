<?php

declare(strict_types=1);

namespace Kopling\Moderation\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Moderation\ModerationReason;
use Kopling\Core\Ux\Context;

/**
 * The "Report" trigger registered into both a Moment's and a Reply's own control slot -- opens
 * a reason + freeform-note modal, posting to the generic `{type}/{id}` report route. Hidden for
 * the flaggable's own author (reporting your own content isn't a real case) -- the route's own
 * `auth` middleware already covers a signed-out visitor, this only avoids showing a dead-end
 * trigger to one specific case `->when()`'s flat permission gate can't express.
 */
class ReportControlEntry extends Component
{
    public function __construct(
        protected Manager $manager,
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $flaggable = $this->context?->getSubject();
        $target = $flaggable
            ? $this->manager->moderationTargets()->first(fn ($candidate) => $candidate->model === get_class($flaggable))
            : null;

        return view('kopling-moderation::ux.report-control-entry', [
            'flaggable' => $flaggable,
            'type' => $target?->alias,
            'isOwnContent' => $flaggable && ($this->context?->isActor($flaggable->person ?? null) ?? false),
            'reasons' => collect(ModerationReason::cases())
                ->mapWithKeys(fn (ModerationReason $reason) => [$reason->value => __("kopling-moderation::moderation.reasons.{$reason->value}")]),
        ]);
    }
}
