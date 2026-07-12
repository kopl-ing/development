<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\LoadOrder;

/**
 * Which side of the declaring extension a rule places the matched extension(s) on -- see
 * `InfluencesLoadOrder::loadOrderRules()`.
 */
enum Directive
{
    case Before;
    case After;
}
