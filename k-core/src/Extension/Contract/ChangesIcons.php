<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

/**
 * An icon pack mapping already-declared `HasIcons` ids to its own icon set's names. Keys are
 * fully-qualified `Icon::$id`s, never auto-prefixed. A key naming an `Icon` that isn't installed
 * is silently ignored; coverage is expected to be partial -- `Ux\Icon` falls back to that
 * `Icon`'s own `$default` for anything unmapped.
 */
interface ChangesIcons
{
    /**
     * @return array<string, string>
     */
    public function iconMap(): array;
}
