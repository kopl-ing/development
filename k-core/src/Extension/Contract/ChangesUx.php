<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Extend\Ux;

/**
 * One contract for every UI surface an extension might want to place something into --
 * a named slot (side navigation today, head assets/post actions/admin widgets later), never
 * a new contract per surface. `Manager::ux()` discovers implementors the same way it does
 * `HasPermissions`/`HasPortals`, and prefixes each declared entry's id (and permission-string
 * condition, if any) with the owning extension's id.
 */
interface ChangesUx
{
    public function ux(): Ux;
}
