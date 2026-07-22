<?php

declare(strict_types=1);

namespace Kopling\Docs\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;
use Kopling\Docs\PageRegistry;

/**
 * Renders the section->pages tree `PageRegistry::tree()` builds from the DB index -- not
 * `Community\Navigation`/`Ux\Portal\Navigation\Item`, which render a flat, extension-declared
 * Ux list. This tree comes from file-scanned front matter instead, a different data shape, so it
 * gets its own leaf component placed into Chrome's generic `docs.sidebar-panel` slot exactly
 * once (same "one entry into a generic slot" pattern `Community\Navigation` itself already is
 * for Admin/Style Guide, just backed by a different data source).
 *
 * `$data`/`$context` are unused but required -- `portal.slot.blade.php` always passes both to
 * whatever `<x-dynamic-component>` a slot resolves.
 */
class Sidebar extends Component
{
    public function __construct(
        protected PageRegistry $registry,
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-docs::ux.sidebar', [
            'tree' => $this->registry->tree(),
        ]);
    }
}
