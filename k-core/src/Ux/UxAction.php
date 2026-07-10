<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

/**
 * What `Manager::ux()` should do with one UxEntry once every extension's operations are
 * collected: `Add` it (the default), `Replace` an already-registered entry's component/data
 * (and optionally its slot/after/before/condition too, if those are also set), or `Remove`
 * an already-registered entry outright. `Replace`/`Remove` target another entry's already
 * fully-qualified id -- same as `after`/`before` -- never a locally-prefixed one.
 */
enum UxAction
{
    case Add;
    case Replace;
    case Remove;
}
