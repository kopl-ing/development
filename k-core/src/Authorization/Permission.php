<?php

declare(strict_types=1);

namespace Kopling\Core\Authorization;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Group;

/**
 * One Group's grant of one permission id -- a row in `group_permission`, never a catalog of
 * every permission that exists (that's `HasPermissions::permissions()`, code-defined). Not to
 * be confused with `Kopling\Core\Extend\Permission`, the declarative value object those declare.
 * `group_permission` has a composite primary key (`group_id`, `permission`), no auto-incrementing
 * `id` -- a grant is only ever queried/created/deleted by its actual columns.
 */
class Permission extends Model
{
    protected $table = 'group_permission';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'permission',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
