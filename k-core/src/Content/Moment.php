<?php

declare(strict_types=1);

namespace Kopling\Core\Content;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class Moment extends Model
{
    use HasUuids;

    protected $fillable = [
        'person_id',
        'title',
        'body',
        'body_html',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
