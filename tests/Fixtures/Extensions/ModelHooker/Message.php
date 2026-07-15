<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelHooker;

use Illuminate\Database\Eloquent\Model;

/**
 * A plain Eloquent model -- deliberately not `Kopling\Core\Database\Model` -- proving
 * `Extend\Model::creating()`/`saving()` need no base-class opt-in, unlike
 * `ModelExtender\Gadget`'s cast() usage.
 */
class Message extends Model
{
    protected $table = 'fixture_messages';

    public $timestamps = false;

    protected $guarded = [];
}
