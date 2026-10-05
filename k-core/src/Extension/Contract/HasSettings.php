<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Ux\Form\Field;

/**
 * Per-person preferences, the counterpart of `HasAdminSettings`; values live in `PersonSettings`.
 */
interface HasSettings
{
    /** @return array<Field> */
    public function settings(): array;
}
