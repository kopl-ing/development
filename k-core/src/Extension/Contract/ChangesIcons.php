<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

/**
 * An icon pack -- e.g. a `kopling/icons-heroicons` extension -- mapping already-declared
 * `HasIcons` ids to its own icon set's names (already-resolvable Blade Icons references, e.g.
 * "heroicon-o-home"). Keys are fully-qualified `Icon::$id`s (written out in full, the same as
 * `Ux::after()`/`Ux::before()` reference another entry's id), never auto-prefixed by Manager --
 * this is a foreign reference to something this extension doesn't own the naming of.
 *
 * A key naming an `Icon` that isn't installed (or was mistyped) is simply ignored, never an
 * error -- same tolerant convention `Ux::after()`/`Ux::before()` already use for a reference that
 * might legitimately not exist yet: an icon pack should be free to map every id it knows about
 * without caring which of them are actually installed on a given site. Coverage is expected to
 * be partial -- not every icon pack has an equivalent for every declared icon -- `Ux\Icon` falls
 * back to that `Icon`'s own `$default` (a Font Awesome name) for anything left unmapped.
 */
interface ChangesIcons
{
    /**
     * @return array<string, string>
     */
    public function iconMap(): array;
}
