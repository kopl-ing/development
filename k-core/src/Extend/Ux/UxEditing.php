<?php

declare(strict_types=1);

namespace Kopling\Core\Extend\Ux;

/**
 * Returned by `Ux::edit()`. No `as()` -- the entry's id is already fixed by the id passed to
 * `edit()`; renaming it here would desync any `after()`/`before()` elsewhere in the same chain
 * still targeting the original id.
 */
class UxEditing extends UxEntryChaining
{
}
