<?php

declare(strict_types=1);

namespace Kopling\Poll;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

class PollOption extends Model
{
    use HasUuids;

    protected $fillable = ['poll_id', 'label', 'emoji', 'position'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function display(): string
    {
        return trim(($this->emoji ?? '').' '.($this->label ?? ''));
    }
}
