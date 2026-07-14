<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

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
 *
 * Lives in `Extend`, not `Authorization`, alongside `Extend\Model`/`Extend\Ux` -- it's what an
 * extension's `HasPermissions::permissions()` declares, the same kind of thing those are, not a
 * persisted/runtime concept. `Kopling\Core\Authorization\Permission` is a different class
 * entirely: the real Eloquent model over a Group's actual granted permissions.
 */
class Permission
{
    /**
     * @param  ?\Closure(\Kopling\Core\People\Person, mixed ...$args): bool  $callback
     */
    public function __construct(
        public string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly ?bool $default = null,
        public readonly ?\Closure $callback = null,
    ) {
    }
}
