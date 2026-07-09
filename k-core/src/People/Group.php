<?php

declare(strict_types=1);

namespace Kopling\Core\People;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
    ];

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class);
    }
}
