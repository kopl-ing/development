<?php

declare(strict_types=1);

namespace Kopling\Moderation\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;

/**
 * The "Hide" trigger -- only ever renders "Hide", never an "Unhide" counterpart, unlike Pin's
 * own dual-state `ControlEntry`: an already-hidden flaggable is excluded from the normal feed/
 * thread by `SoftDeletes`'s own global scope, so its card never renders through this component
 * to begin with -- there's no reachable "already hidden" state here to render instead. Unhide
 * lives only in the moderation queue's own row template (see `ModerationController::hide()`'s
 * docblock).
 */
class HideControlEntry extends Component
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

        return view('kopling-moderation::ux.hide-control-entry', [
            'flaggable' => $flaggable,
            'type' => $target?->alias,
        ]);
    }
}
