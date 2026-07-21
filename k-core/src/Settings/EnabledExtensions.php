<?php

declare(strict_types=1);

namespace Kopling\Core\Settings;

/**
 * Persists which installed extensions are enabled, as a JSON array under one `Settings` key.
 * `all()` returning `null` is the bootstrap state -- nothing has ever been toggled, so
 * `isEnabled()` treats everything as enabled. The first `enable()`/`disable()` call seeds the
 * list from the caller-supplied `$allIds` before applying the one change.
 */
class EnabledExtensions
{
    protected const KEY = 'extensions-enabled';

    /**
     * @return array<string>|null
     */
    public static function all(): ?array
    {
        $value = Settings::get(self::KEY);

        if ($value === null) {
            return null;
        }

        return json_decode($value, true) ?? [];
    }

    public static function isEnabled(string $id): bool
    {
        $all = static::all();

        return $all === null || in_array($id, $all, true);
    }

    /**
     * @param  array<string>  $allIds  Every currently discovered extension id -- only consulted
     *                                 to bootstrap the list on its very first write.
     */
    public static function enable(string $id, array $allIds): void
    {
        $enabled = static::all() ?? $allIds;

        if (! in_array($id, $enabled, true)) {
            $enabled[] = $id;
        }

        static::persist($enabled);
    }

    /**
     * @param  array<string>  $allIds
     */
    public static function disable(string $id, array $allIds): void
    {
        $enabled = array_values(array_diff(static::all() ?? $allIds, [$id]));

        static::persist($enabled);
    }

    /**
     * @param  array<string>  $enabled
     */
    protected static function persist(array $enabled): void
    {
        Settings::set(self::KEY, json_encode(array_values($enabled)));
    }
}
