<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelExtender;

use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $table = 'fixture_parts';

    public $timestamps = false;

    protected $guarded = [];
}
