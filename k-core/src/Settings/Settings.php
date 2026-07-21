<?php

declare(strict_types=1);

namespace Kopling\Core\Settings;

use Illuminate\Support\Facades\DB;

/**
 * A flat key/value store for `HasAdminSettings`-declared field values -- a plain `DB::table()`
 * helper, not an Eloquent model. `$key` is always an already-prefixed `Field::$id`.
 *
 * TODO: values round-trip as raw strings, callers `json_encode()`/`json_decode()` by hand. A
 * typed casting layer is deferred, not designed yet.
 *
 * `get()` catches `\RuntimeException`, not just `\PDOException` -- `EnabledExtensions` reads
 * through here even from bare Unit tests that boot no Laravel app at all, where `DB::table()`
 * fails with the facade's own "root has not been set" `\RuntimeException` before any PDO call.
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = DB::table('settings')->where('key', $key)->value('value');
        } catch (\RuntimeException $e) {
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
