<?php

declare(strict_types=1);

namespace Kopling\Moderation\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;

/**
 * The "Delete" trigger -- reuses the same `$target->softDeletable` flag `HideControlEntry` does
 * (a `SoftDeletes` model always supports both `delete()` and `forceDelete()`), gated `moderate`.
 * Single-state, same reasoning as `HideControlEntry`'s own docblock: `forceDelete()` is
 * irreversible, so there's no "undo" state to render here either.
 */
class DeleteControlEntry extends Component
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

        return view('kopling-moderation::ux.delete-control-entry', [
            'flaggable' => $flaggable,
            'type' => $target?->alias,
        ]);
    }
}
