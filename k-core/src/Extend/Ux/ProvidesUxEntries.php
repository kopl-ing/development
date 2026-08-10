<?php

declare(strict_types=1);

namespace Kopling\Core\Extend\Ux;

use Illuminate\Support\Collection;
use Kopling\Core\Ux\UxEntry;

/**
 * Implemented by `Ux` itself and every staged object its chain can end on (`UxAdding`,
 * `UxEditing`, `UxReplacing`) -- lets `ChangesUx::ux()` return whichever one a chain happens to
 * stop at, instead of forcing every implementor back to a bare `Ux`.
 */
interface ProvidesUxEntries
{
    /**
     * @return Collection<int, UxEntry>
     */
    public function entries(): Collection;
}
