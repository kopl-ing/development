<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

/**
 * A marker, no methods -- whatever eventually lets an admin disable an installed extension
 * (not built yet) must refuse to for any `AbstractExtension` implementing this. `Core` is
 * the first implementor (it's not a real "disableable" extension at all), but the same
 * marker covers a hosting provider bundling an extension into their install that they don't
 * want an admin able to turn off -- not just Core's own case.
 */
interface CannotBeDisabled
{
}
