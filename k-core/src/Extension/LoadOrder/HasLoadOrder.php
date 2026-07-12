<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\LoadOrder;

/**
 * Explicit, self-declared load-order constraints -- Composer package names this extension
 * needs to load after or before. This is the escape hatch: it always wins over whatever
 * `InfluencesLoadOrder` infers for the same target package, so an extension can opt out of
 * an inferred default (e.g. the rare settings-providing extension that genuinely needs to
 * load before Admin instead of after it). A reference to a package that isn't installed is
 * ignored, never an error -- same graceful-degradation rule `Ux\SlotResolver` applies to a
 * dangling `after`/`before`.
 */
interface HasLoadOrder
{
    /** @return array<string> Composer package names this extension must load after. */
    public function loadAfter(): array;

    /** @return array<string> Composer package names this extension must load before. */
    public function loadBefore(): array;
}
