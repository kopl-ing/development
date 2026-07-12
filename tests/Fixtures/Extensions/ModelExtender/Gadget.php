<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelExtender;

use Kopling\Core\Database\Model;

/**
 * Extends `Kopling\Core\Database\Model` (not plain Eloquent) specifically because that's the
 * base whose `getCasts()` reads `Manager::models()`'s registered casts -- a plain Eloquent model
 * wouldn't pick up an `ExtendsModels`-declared cast at all, and the fixture needs to prove that
 * it does.
 */
class Gadget extends Model
{
    protected $table = 'fixture_gadgets';

    public $timestamps = false;

    protected $guarded = [];
}
