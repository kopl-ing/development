<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\People\Person as CorePerson;
use Laravel\Passport\Token as PassportToken;

class Token extends PassportToken
{
    public function person(): BelongsTo
    {
        return $this->belongsTo(CorePerson::class, 'user_id');
    }
}
