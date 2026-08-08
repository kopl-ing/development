<?php

declare(strict_types=1);

namespace Kopling\Moderation\Event;

use Illuminate\Database\Eloquent\Model;
use Kopling\Core\People\Person;

/**
 * Fired once a flaggable is actually `forceDelete()`d -- never for a soft "hide" (Phase 1),
 * which is reversible by design and must not trigger cascade cleanup. This is the extension
 * point: any extension owning resources attached to a flaggable (a future images/attachments
 * extension, say) listens for this via its own `ListensToEvents`, `class_exists`-guarded, the
 * same soft-dependency shape `reactions` already uses for `Reply` -- `moderation` never learns
 * those resources exist. `$subject` is force-deleted by the time listeners run, but its
 * in-memory attributes (id, etc.) are still readable, so a listener can still find and clean up
 * whatever it owns keyed by this model's id.
 */
class ContentDeleted
{
    public function __construct(
        public Model $subject,
        public ?string $reason,
        public Person $moderator,
    ) {
    }
}
