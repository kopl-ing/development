<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\LoadOrder;

/**
 * Explicit, self-declared "must load after" constraint -- Composer package names this
 * extension needs to load after. Split from `LoadsBefore` (they used to be one `HasLoadOrder`
 * interface) so an extension that only ever needs one direction -- the common case -- doesn't
 * have to declare a no-op `loadBefore(): array { return []; }` just to satisfy a method it has
 * no opinion about; same "implement zero, one, or many" opt-in principle every other contract
 * here already follows. This is the escape hatch: it always wins over whatever
 * `InfluencesLoadOrder` infers for the same target package, so an extension can opt out of an
 * inferred default (e.g. the rare settings-providing extension that genuinely needs to load
 * before Admin instead of after it). A reference to a package that isn't installed is ignored,
 * never an error -- same graceful-degradation rule `Ux\SlotResolver` applies to a dangling
 * `after`/`before`.
 */
interface LoadsAfter
{
    /** @return array<string> Composer package names this extension must load after. */
    public function loadAfter(): array;
}
