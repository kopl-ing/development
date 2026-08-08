<?php

declare(strict_types=1);

namespace Kopling\Moderation;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kopling\Core\Database\Model;
use Kopling\Core\Moderation\ModerationReason;
use Kopling\Core\People\Person;

/**
 * A single report against a single flaggable -- a Moment, a Reply, a Person, or anything else
 * a `RegistersModerationTargets` implementor declares. Mirrors `reactions`' own `Reaction`
 * (polymorphic `reactable_type`/`reactable_id`) shape exactly, just for reports instead of
 * emoji. `status`/`resolved_*` are orthogonal to whether the flaggable itself is hidden or
 * deleted -- see `ContentModerator`.
 */
class Flag extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'flaggable_type',
        'flaggable_id',
        'person_id',
        'reason',
        'note',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ModerationReason::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'resolved_by');
    }

    /**
     * Resolves a `{type}/{id}` route pair into the real flaggable model -- `$type` must already
     * be a registered `Manager::moderationTargets()` alias (which also means it's a registered
     * morph-map alias, see `AggregatesModerationTargets`); never falls back to a raw class name,
     * the same IDOR-safe reasoning `Reaction::resolveReactable()` documents.
     *
     * Includes soft-deleted rows when the resolved class actually supports them -- `Person`
     * doesn't use `SoftDeletes` at all, so calling `withTrashed()` unconditionally would throw;
     * for a type that does, this is what lets `unhide()` find an already-hidden row, which the
     * global `SoftDeletes` scope would otherwise exclude by default.
     */
    public static function resolveFlaggable(string $type, string $id): EloquentModel
    {
        $class = Relation::getMorphedModel($type);

        abort_unless($class !== null && class_exists($class), 404);

        $query = $class::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }
}
