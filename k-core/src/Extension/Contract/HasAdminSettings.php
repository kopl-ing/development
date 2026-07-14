<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Ux\Form\Field;

/**
 * Declares one or more admin-editable settings -- what each one is (label/description/default,
 * which `Ux\Form\*` component renders it), never how it's persisted or where it renders. Admin
 * owns both of those, the same split `RequestsStorageDriver`/`StorageRequest` already
 * established for storage drives: an extension asks for what it needs, the extension that owns
 * the concern (here, `kopling/admin`) decides the backend/placement.
 *
 * Deliberately not named `HasSettings` -- a future per-person preferences contract needs that
 * name free without colliding with this one.
 */
interface HasAdminSettings
{
    /** @return array<Field> */
    public function adminSettings(): array;
}
