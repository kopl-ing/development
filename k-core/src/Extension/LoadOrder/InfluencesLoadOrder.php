<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\LoadOrder;

/**
 * Lets an extension place load-order constraints on *other* extensions, dispatched by
 * capability contract rather than by package name -- so the extension that owns a contract
 * (e.g. Admin owning a future `HasSettings`) can require anything implementing it to load
 * after/before itself without ever knowing which packages -- Kopling's own or a community
 * author's -- will implement it. Every rule is relative to the declaring extension's own
 * package (`Resolver` already knows that from iterating `Manager::extensions()`, so there's
 * nothing to name here); `Resolver` applies one edge per other extension it finds implementing
 * the given contract. Loses to a matched extension's own `HasLoadOrder` for the same pair --
 * see that interface.
 */
interface InfluencesLoadOrder
{
    /** @return array<class-string, Directive> */
    public function loadOrderRules(): array;
}
