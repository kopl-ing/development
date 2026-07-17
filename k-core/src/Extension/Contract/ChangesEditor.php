<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Ux\Editor\EditorNode;

/**
 * Votes which TipTap node/mark types the shared editor bundle should enable -- not an
 * independently-namespaced declaration the way `HasIcons`/`HasPermissions` are (nothing here
 * is ever prefixed by `Manager`), but a vote into one shared, closed catalog, same reasoning
 * `ChangesTheme` already uses `Token` for. Every case is additive-only in v1: there's no way
 * for an extension to disable one of Core's own defaults, mirroring `ChangesTheme`'s own
 * shape.
 *
 * Kept deliberately this narrow -- pairing an enabled node with an arbitrary per-extension
 * renderer callable and JS config blob would let an extension "enable" a node type the one
 * shared JS bundle doesn't actually implement, silently producing dead configuration. A
 * closed, Core-owned catalog (`EditorNode`) makes that structurally impossible: `Manager::
 * editorNodes()` only ever aggregates which of that fixed set are on, `DocumentRenderer`'s own
 * node/mark-to-HTML mapping is tied 1:1 to the same enum, and the JS bundle already ships every
 * case's real TipTap extension regardless of whether it's enabled anywhere.
 */
interface ChangesEditor
{
    /**
     * @return array<EditorNode>
     */
    public function editor(): array;
}
