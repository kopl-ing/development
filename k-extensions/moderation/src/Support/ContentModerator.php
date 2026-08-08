<?php

declare(strict_types=1);

namespace Kopling\Moderation\Support;

use Illuminate\Database\Eloquent\Model;
use Kopling\Core\People\Person;
use Kopling\Moderation\Event\ContentDeleted;
use Kopling\Moderation\Flag;

/**
 * The single place that keeps a flaggable's actor/reason write and its actual soft-delete
 * atomic, and bulk-resolves whatever pending `Flag`s prompted the action -- so no call site can
 * do one of those without the other. See .docs/planning/moderation-extension-plan.md.
 */
class ContentModerator
{
    public function hide(Model $flaggable, Person $moderator, ?string $reason): void
    {
        $flaggable->forceFill(['deleted_by' => $moderator->id, 'deleted_reason' => $reason])->save();
        $flaggable->delete();

        $this->resolvePendingFlags($flaggable, $moderator);
    }

    /**
     * `restore()` alone only clears `deleted_at` (a targeted update, same as `delete()` itself)
     * -- `deleted_by`/`deleted_reason` need their own explicit clear.
     */
    public function unhide(Model $flaggable): void
    {
        $flaggable->restore();
        $flaggable->forceFill(['deleted_by' => null, 'deleted_reason' => null])->save();
    }

    /**
     * The cascade trigger, unlike `hide()` -- `$flaggable` is genuinely gone after this, so
     * `ContentDeleted` fires explicitly here (`forceDelete()` has no built-in hook to carry a
     * reason/moderator through the way `delete()`/`restore()` don't need one either). Sets
     * `deleted_by`/`deleted_reason` *before* force-deleting so a listener reading `$event->
     * subject` still sees them, even though the row itself won't persist.
     */
    public function delete(Model $flaggable, Person $moderator, ?string $reason): void
    {
        $flaggable->forceFill(['deleted_by' => $moderator->id, 'deleted_reason' => $reason])->save();

        $this->resolvePendingFlags($flaggable, $moderator);

        $flaggable->forceDelete();

        event(new ContentDeleted($flaggable, $reason, $moderator));
    }

    protected function resolvePendingFlags(Model $flaggable, Person $moderator): void
    {
        Flag::where('flaggable_type', $flaggable->getMorphClass())
            ->where('flaggable_id', $flaggable->getKey())
            ->where('status', Flag::STATUS_PENDING)
            ->update([
                'status' => Flag::STATUS_ACTIONED,
                'resolved_by' => $moderator->id,
                'resolved_at' => now(),
            ]);
    }
}
