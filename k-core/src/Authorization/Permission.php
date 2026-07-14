<?php

declare(strict_types=1);

namespace Kopling\Core\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\People\Group;

/**
 * One Group's grant of one permission id -- a row in `group_permission`, never a catalog of
 * every permission that exists. A permission's own definition (id/label/description/default/
 * callback) stays entirely code-defined, declared via `HasPermissions::permissions()` and
 * computed fresh on every request by `Manager::permissions()` -- see that migration's own
 * comment. This model exists to replace the raw `DB::table('group_permission')` queries
 * `People\Group` used to run by hand with a real Eloquent relation; it never gets a `label`/
 * `description` of its own, and there's nothing to fetch it "by id" for.
 *
 * Not to be confused with `Kopling\Core\Extend\Permission` -- the declarative value object an
 * extension's `HasPermissions::permissions()` returns. That class used to live at this
 * namespace/name; it moved to `Extend` to free this one up for the real model.
 *
 * `group_permission` has a composite primary key (`group_id`, `permission`), not an
 * auto-incrementing `id` -- fine here since a grant is only ever queried/created/deleted by its
 * actual columns through `Group::permissions()`, never fetched or saved by a single id.
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
