<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Portal\PortalExtension;

/**
 * Attaches routes/js/css to a Portal -- any Portal, whether declared by this same extension or
 * by another one entirely -- the same relationship `ChangesUx` has to a slot it doesn't own.
 * `HasPortals` only ever declares a Portal's identity (id/label/path/layout/permission); it
 * never carries what actually renders under it. This is deliberately the only way anything ends
 * up grouped under a Portal's route prefix/name/middleware, including for the extension that
 * declared the Portal in the first place -- one mechanism, not a shortcut for the owner plus a
 * contract for everyone else.
 */
interface ExtendsPortals
{
    /** @return array<PortalExtension> */
    public function extendsPortals(): array;
}
