<?php

declare(strict_types=1);

namespace Kopling\Poll;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Content\Moment;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

class Poll extends Model
{
    use HasUuids;

    public const VISIBILITY_ALWAYS = 'always';

    public const VISIBILITY_AFTER_VOTE = 'after_vote';

    public const VISIBILITY_AFTER_CLOSE = 'after_close';

    public const VISIBILITY_OPTIONS = [
        self::VISIBILITY_ALWAYS,
        self::VISIBILITY_AFTER_VOTE,
        self::VISIBILITY_AFTER_CLOSE,
    ];

    protected $fillable = [
        'moment_id', 'question', 'multiple_choice', 'max_choices', 'results_visibility', 'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'multiple_choice' => 'boolean',
            'closes_at' => 'datetime',
        ];
    }

    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    public function isVisibleTo(?Person $person): bool
    {
        if ($this->groups->isEmpty()) {
            return true;
        }

        return $person !== null
            && $person->groups->pluck('id')->intersect($this->groups->pluck('id'))->isNotEmpty();
    }

    public function hasVoted(?Person $person): bool
    {
        return $person !== null && $this->votes->contains('person_id', $person->id);
    }

    public function resultsVisibleTo(?Person $person): bool
    {
        return match ($this->results_visibility) {
            self::VISIBILITY_ALWAYS => true,
            self::VISIBILITY_AFTER_CLOSE => $this->isClosed(),
            default => $this->isClosed() || $this->hasVoted($person),
        };
    }
}
