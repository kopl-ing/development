<?php

declare(strict_types=1);

namespace Kopling\Core\Authorization;

/**
 * A named, granular capability -- never a hardcoded "is admin" check. `$id` is set by the
 * author (core or an extension) as just the local part, e.g. "manage-people"; Manager
 * prefixes it with the extension's own id (or "core") before it's ever registered with the
 * Gate -- "kopling-example::manage-things", same `::` separator as views and translations --
 * so an author never has to think about collisions with another extension's names.
 *
 * `$callback` is an escape hatch, not the default path: most permissions are a flat "does
 * this person hold it via one of their groups" check, registered with no callback at all.
 * When present, it's an *additional* condition layered on top of that base grant check
 * (e.g. "must hold the permission AND own this specific record") -- it can never grant
 * access on its own.
 */
class Permission
{
    /**
     * @param  ?\Closure(\Kopling\Core\People\Person, mixed ...$args): bool  $callback
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly ?\Closure $callback = null,
    ) {
    }
}
