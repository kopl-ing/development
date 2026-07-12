<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Theme;

use Illuminate\Database\Eloquent\Model;

/**
 * One ad-hoc, per-token admin override row, layered on top of whatever the active `ChangesTheme`
 * extension declares (see `Kopling\Core\Ux\Theme::resolve()`) -- a missing row means "use
 * whatever the active theme (or, failing that, the compiled default) already says for this
 * token." No admin editor writes to this yet; the table exists so `Theme::resolve()` has
 * somewhere real to read the highest-priority layer from once one does.
 */
class ThemeToken extends Model
{
    protected $table = 'theme_tokens';

    protected $primaryKey = 'token';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'token',
        'value',
    ];
}
