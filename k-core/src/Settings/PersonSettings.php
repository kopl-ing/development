<?php

declare(strict_types=1);

namespace Kopling\Core\Settings;

use Illuminate\Support\Facades\DB;
use Kopling\Core\People\Person;

/**
 * `Settings`' per-person counterpart, for `HasSettings`-declared field values.
 */
class PersonSettings
{
    public static function get(Person $person, string $key, mixed $default = null): mixed
    {
        return static::all($person)[$key] ?? $default;
    }

    /**
     * @return array<string, ?string>
     */
    public static function all(Person $person): array
    {
        return DB::table('person_settings')
            ->where('person_id', $person->getKey())
            ->pluck('value', 'key')
            ->all();
    }

    public static function set(Person $person, string $key, mixed $value): void
    {
        DB::table('person_settings')->updateOrInsert(
            ['person_id' => $person->getKey(), 'key' => $key],
            ['value' => $value, 'updated_at' => now()],
        );
    }

    public static function forget(Person $person, string $key): void
    {
        DB::table('person_settings')->where('person_id', $person->getKey())->where('key', $key)->delete();
    }
}
