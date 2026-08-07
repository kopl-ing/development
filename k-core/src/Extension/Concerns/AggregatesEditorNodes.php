<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Kopling\Core\Extension\Contract\ChangesEditor;
use Kopling\Core\Ux\Editor\EditorNode;

trait AggregatesEditorNodes
{
    /**
     * Every `EditorNode` any extension has voted to enable, deduped by value -- these are votes
     * into one shared catalog, not independently-namespaced declarations, so nothing is prefixed.
     *
     * @return array<EditorNode>
     */
    public function editorNodes(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return array_map(fn (string $value) => EditorNode::from($value), $cached['editorNodes']);
        }

        $nodes = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ChangesEditor) {
                continue;
            }

            $declared = collect($extension->editor())->ensure(EditorNode::class);

            foreach ($declared as $node) {
                $nodes[$node->value] = $node;
            }
        }

        return array_values($nodes);
    }
}
