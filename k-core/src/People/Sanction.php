<?php

declare(strict_types=1);

namespace Kopling\Core\People;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\Moderation\Event\PersonSanctioned;
use Kopling\Core\Moderation\Event\PersonSanctionLifted;
use Kopling\Core\Moderation\ModerationReason;

/**
 * Append-only audit log for `Person` sanctions, and the single entry point that keeps the log
 * write and the person's own live state columns atomic -- `issue()`/`lift()` are the only way
 * either should ever change, never a bare `Person::forceFill()` elsewhere. Directly answers
 * Discourse's own documented gap: some staff actions historically weren't logged because logging
 * wasn't structurally tied to the action -- here it can't be skipped, since applying a sanction
 * *is* creating this row.
 */
class Sanction extends Model
{
    use HasUuids;

    protected $fillable = [
        'person_id',
        'issued_by',
        'lifted_by',
        'communication_blocked',
        'visibility',
        'access_blocked',
        'access_blocked_until',
        'reason',
        'note',
        'issued_at',
        'lifted_at',
    ];

    protected function casts(): array
    {
        return [
            'communication_blocked' => 'boolean',
            'access_blocked' => 'boolean',
            'access_blocked_until' => 'datetime',
            'reason' => ModerationReason::class,
            'issued_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'issued_by');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'lifted_by');
    }

    /**
     * Each call fully re-specifies all three axes -- never a partial patch onto whatever the
     * previous sanction left in place -- so any still-active previous sanction for this person
     * is superseded (its own `lifted_at` stamped, `lifted_by` left `null` to distinguish
     * "superseded by a new sanction" from an explicit `lift()` call) before the new row and the
     * person's live columns are written, keeping "at most one active sanction per person" a real
     * invariant rather than something the audit log could silently disagree with.
     *
     * @param  array{communication_blocked?: bool, visibility?: ?string, access_blocked?: bool, access_blocked_until?: ?\DateTimeInterface, reason: string, note?: ?string}  $attributes
     */
    public static function issue(Person $person, array $attributes, Person $issuedBy): self
    {
        static::query()
            ->where('person_id', $person->id)
            ->whereNull('lifted_at')
            ->update(['lifted_at' => now()]);

        $communicationBlocked = $attributes['communication_blocked'] ?? false;
        $accessBlocked = $attributes['access_blocked'] ?? false;
        $visibility = $attributes['visibility'] ?? null;

        $sanction = static::create([
            'person_id' => $person->id,
            'issued_by' => $issuedBy->id,
            'communication_blocked' => $communicationBlocked,
            'visibility' => $visibility,
            'access_blocked' => $accessBlocked,
            'access_blocked_until' => $accessBlocked ? ($attributes['access_blocked_until'] ?? null) : null,
            'reason' => $attributes['reason'],
            'note' => $attributes['note'] ?? null,
            'issued_at' => now(),
        ]);

        $person->forceFill([
            'communication_blocked_at' => $communicationBlocked ? now() : null,
            'visibility' => $visibility ?? 'normal',
            'access_blocked_at' => $accessBlocked ? now() : null,
            'access_blocked_until' => $accessBlocked ? ($attributes['access_blocked_until'] ?? null) : null,
        ])->save();

        event(new PersonSanctioned($person, $sanction));

        return $sanction;
    }

    /**
     * Finds `$person`'s own currently-active sanction (there is at most one, see `issue()`'s own
     * docblock) and lifts it -- `null` if nothing is currently active. Takes a `Person`, not a
     * specific `Sanction`, for the same reason `issue()` does: a caller (a queue row's own "Lift"
     * button) has the person in hand, not an opaque sanction id to look up first.
     */
    public static function lift(Person $person, Person $liftedBy): ?self
    {
        $sanction = static::query()
            ->where('person_id', $person->id)
            ->whereNull('lifted_at')
            ->latest('issued_at')
            ->first();

        if ($sanction === null) {
            return null;
        }

        $sanction->update(['lifted_at' => now(), 'lifted_by' => $liftedBy->id]);

        $person->forceFill([
            'communication_blocked_at' => null,
            'visibility' => 'normal',
            'access_blocked_at' => null,
            'access_blocked_until' => null,
        ])->save();

        event(new PersonSanctionLifted($person, $sanction));

        return $sanction;
    }
}
