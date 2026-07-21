<?php

declare(strict_types=1);

namespace Kopling\Poll;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class PollVote extends Model
{
    use HasUuids;

    protected $fillable = ['poll_id', 'poll_option_id', 'person_id'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
