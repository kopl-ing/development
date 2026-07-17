<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\NotionEditor;

/**
 * The swappable editor mount -- resolves/renders `SLOT` exactly like `Card\Body`/`Top` do, so
 * an extension can swap the whole editor implementation with the same `Ux::replace()` call it
 * already knows, e.g. `Ux::make()->replace('kopling-core::notion', 'acme-editor::mount')` (the
 * id is `kopling-core::notion` -- Core's own package prefix plus the local `as('notion')` name
 * below, not slot-namespaced -- see `EditorReplacer`'s own test fixture for a worked example).
 * v1 ships exactly one implementation (`NotionEditor`) registered as its own default, same "each
 * component declares its own defaults on itself" rule `Core::ux()` already follows -- a second,
 * genuinely alternative editor is a v2 concern (needs real per-extension JS bundling, which
 * doesn't exist yet), but the swap mechanism itself needs no rework to support one later.
 *
 * `$context` defaults to `null`: a compose form has no bound `Moment`/`Reply` yet (nothing to
 * bind), consistent with `Card\Content`/`Card\Avatar`'s own `?Context $context = null` default,
 * not a special case invented for this component.
 */
class Editor extends Component
{
    public const SLOT = 'kopling-core::editor.body';

    public ?UxEntry $entry;

    public function __construct(
        Manager $manager,
        public string $name = 'body',
        public ?string $value = null,
        public ?string $placeholder = null,
        public ?Context $context = null,
    ) {
        $this->entry = SlotResolver::resolve(self::SLOT, $manager->ux(), $context)->first();
    }

    public function render(): View
    {
        return view('kopling-core::editor.mount');
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(NotionEditor::class)->in(self::SLOT)->as('notion');
    }
}
