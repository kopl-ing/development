<?php

declare(strict_types=1);

namespace Kopling\Core\Settings;

use Illuminate\Support\Facades\DB;

/**
 * A flat key/value store for `HasAdminSettings`-declared field values -- deliberately a plain
 * `DB::table()` helper, not an Eloquent model, same choice `People\Group::hasPermission()`/
 * `givePermissionTo()` already made for its own raw `group_permission` pivot: there's no
 * relation, no cast, nothing an Eloquent model would earn its keep for, just get-by-key and
 * upsert-by-key against one flat table. `$key` is always a `Field::$id` already prefixed by
 * `Manager::adminSettings()` (e.g. "kopling-reactions::enabled"), so two extensions' fields can
 * never collide here either. Also backs `EnabledExtensions` and any ad-hoc per-extension key an
 * extension wants to persist (`value` is `longText`, deliberately large -- can hold anything).
 *
 * TODO: values round-trip as raw strings -- callers `json_encode()`/`json_decode()` by hand at
 * each call site (see `EnabledExtensions`). A typed casting layer (mirroring `Extend\Model::
 * cast()`) is deferred, not designed yet.
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = DB::table('settings')->where('key', $key)->value('value');
        } catch (\PDOException $e) {
            return $default;
        }

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()],
        );
    }
}
