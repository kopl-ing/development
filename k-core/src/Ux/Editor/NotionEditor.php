<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Editor;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;

/**
 * v1's one and only editor implementation -- a Notion-styled TipTap editor built from free/MIT
 * TipTap primitives (not the paid tiptap.dev "Notion-like" template, which requires a Tiptap
 * Start-plan subscription and React; see the editor integration's own decision entry).
 *
 * A leaf `UxEntry` target, so it follows `UxEntry`'s mandatory shape: a single `array $data`
 * constructor param, not a spread of named props -- `Editor`/`SlotResolver` never need to know
 * this component's individual prop names, same as `Card\Content`'s own leaf shape.
 */
class NotionEditor extends Component
{
    public function __construct(
        protected Manager $manager,
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-core::editor.notion', [
            'name' => $this->data['name'] ?? 'body',
            'value' => $this->data['value'] ?? null,
            'placeholder' => $this->data['placeholder'] ?? null,
            'nodes' => array_map(fn (EditorNode $node) => $node->value, $this->manager->editorNodes()),
        ]);
    }
}
