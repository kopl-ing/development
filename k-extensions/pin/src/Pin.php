<?php

declare(strict_types=1);

namespace Kopling\Pin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

class Pin extends Model
{
    use HasUuids;

    /**
     * Fixed set of reasons, each mapped to one daisyUI semantic color suffix -- no free-text
     * reason, no free swatch picker, per the standing decision.
     */
    public const REASONS = [
        'announcement' => 'info',
        'event' => 'accent',
        'important' => 'warning',
        'help' => 'success',
    ];

    protected $fillable = ['moment_id', 'reason', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function color(): string
    {
        return self::REASONS[$this->reason] ?? 'neutral';
    }

    public function isActive(): bool
    {
        $now = now();

        return ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gte($now));
    }

    public function isVisibleTo(?Person $person): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->groups->isEmpty()) {
            return true;
        }

        return $person !== null
            && $person->groups->pluck('id')->intersect($this->groups->pluck('id'))->isNotEmpty();
    }

    /**
     * Every currently active pin visible to $person, eager-loaded with its moment and groups --
     * deliberately a PHP filter over all pins rather than one complex SQL join: pins are
     * curated/rare by nature (not a high-cardinality table), so this stays simple. Revisit only
     * if that assumption breaks.
     *
     * @return Collection<int, Pin>
     */
    public static function visibleFor(?Person $person): Collection
    {
        return static::query()
            ->with(['moment', 'groups'])
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->get()
            ->filter(fn (Pin $pin) => $pin->isVisibleTo($person))
            ->values();
    }
}
