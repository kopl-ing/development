<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\LoadOrder;

/**
 * Explicit, self-declared "must load before" constraint -- the inverse of `LoadsAfter`, split
 * out for the same reason: most extensions only ever need one direction, and implementing this
 * one too should never be the price of implementing that one.
 */
interface LoadsBefore
{
    /** @return array<string> Composer package names this extension must load before. */
    public function loadBefore(): array;
}
