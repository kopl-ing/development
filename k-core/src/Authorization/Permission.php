<?php

declare(strict_types=1);

namespace Kopling\Core\Authorization;

use Kopling\Core\People\Person;

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
     * @param  ?\Closure(?Person, mixed ...$args): bool  $callback  `Person` is nullable: a
     *         default-granted permission reaches the callback for guests too (e.g. "may
     *         view, but only non-hidden records").
     */
    public function __construct(
        public string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly ?bool $default = null,
        public readonly ?\Closure $callback = null,
    ) {
    }

    /**
     * The decision every registered Gate ability delegates to, in exactly the precedence the
     * class docblock states. The base grant first: held via one of the person's groups, else
     * this permission's declared default. Then `$callback`, when present, as a further
     * condition on top of that grant -- narrowing "may reply" down to "and owns this
     * specific record" via whatever the Gate check passed along in `$args`. A denied base
     * grant is final: the callback is never consulted, so it can never grant access on its
     * own.
     */
    public function authorize(?Person $person, mixed ...$args): bool
    {
        $granted = ($person?->hasPermission($this->id) ?? false) || ($this->default ?? false);

        if (! $granted) {
            return false;
        }

        return $this->callback === null || (bool) ($this->callback)($person, ...$args);
    }
}
