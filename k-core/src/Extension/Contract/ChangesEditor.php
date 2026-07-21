<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Ux\Editor\EditorNode;

/**
 * Votes which TipTap node/mark types the shared editor bundle should enable -- a vote into one
 * shared, closed catalog (`EditorNode`), never prefixed by `Manager`, additive-only: nothing can
 * disable one of Core's own defaults. Deliberately closed rather than an arbitrary per-extension
 * renderer, so an extension can't "enable" a node type the shared JS bundle doesn't implement.
 */
interface ChangesEditor
{
    /**
     * @return array<EditorNode>
     */
    public function editor(): array;
}
