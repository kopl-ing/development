<?php

declare(strict_types=1);

namespace Kopling\Core\Extend\Ux;

/**
 * Returned by `Ux::replace()`. No `as()` -- the target id is fixed by the id passed to
 * `replace()`. No `first()`/`flush()`/`priority()` -- `AggregatesUx::applyUxReplace()` doesn't
 * copy those fields onto the entry being replaced.
 */
class UxReplacing extends UxChaining
{
}
