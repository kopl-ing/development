<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelExtender;

use Illuminate\Database\Eloquent\Model;
use Kopling\Core\Database\Concerns\HasExtendedCasts;

/**
 * Deliberately does NOT extend `Kopling\Core\Database\Model` -- mimics `Person`'s own real
 * constraint (a model that must extend something else, so it can only `use HasExtendedCasts`
 * directly) to prove that path shares the exact same cast registry a `Database\Model` subclass
 * like `Gadget` does, not an independent, always-empty copy of its own.
 */
class Widget extends Model
{
    use HasExtendedCasts;

    protected $table = 'fixture_widgets';

    public $timestamps = false;

    protected $guarded = [];
}
