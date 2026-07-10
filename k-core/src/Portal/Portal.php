<?php

declare(strict_types=1);

namespace Kopling\Core\Portal;

/**
 * A named UI surface -- a route prefix + the Blade layout its routes render inside. Never a
 * gating mechanism itself: routes registered under a Portal still check their own granular
 * `Kopling\Core\Authorization\Permission` exactly as they would anywhere else (see the charter,
 * D29 -- a Portal is never a disguised "is admin" flag).
 */
class Portal
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $path,
        public readonly string $layout,
    ) {
    }
}
