<?php

declare(strict_types=1);

namespace Kopling\Core\Portal;

/**
 * A named UI surface -- a route prefix + the Blade layout its routes render inside. Never a
 * gating mechanism itself: routes registered under a Portal still check their own granular
 * `Kopling\Core\Extend\Permission` exactly as they would anywhere else (see the charter,
 * D29 -- a Portal is never a disguised "is admin" flag).
 *
 * Carries no routes/js/css of its own -- not even for the extension that declares it. See
 * `Kopling\Core\Extension\Contract\ExtendsPortals`/`PortalExtension` for how anything actually
 * ends up registered under a Portal; this is deliberately the only mechanism, so a Portal with
 * nothing yet attached to it (silently registering zero routes, as `kopling/admin`'s did before
 * this split) is exactly as discoverable as one that does.
 */
class Portal
{
    public function __construct(
        public string $id,
        public readonly string $label,
        public readonly string $path,
        public readonly string $layout,
        public readonly ?string $icon = null,
        public readonly ?string $description = null,
        public ?string $permission = null,
    ) {
    }
}
