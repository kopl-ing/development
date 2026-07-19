<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal\Navigation;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * The common case for a side-navigation entry -- just a link. Anything richer (a badge
 * count, custom markup) ships its own component instead and registers that FQCN with
 * Ux::add() -- this one is a convenience, not a requirement.
 *
 * `$data['icon']`, when given, is a semantic icon id declared via `HasIcons::icons()` (e.g.
 * "kopling-core::home"), rendered through `<x-k::icon>` -- see `Kopling\Core\Ux\Icon` for how
 * that resolves to a concrete icon (the active pack's own, or its Font Awesome default).
 *
 * `$data['hideOnPortal']`, when given, is a fully-qualified Portal id (e.g.
 * "kopling-core::community") -- this entry renders nothing while `$context->isPortal()` says
 * that's the portal currently being viewed, so a link to the very portal you're already on
 * doesn't show up pointing at itself (the community/admin/style-guide links in `UserMenu::SLOT`
 * use this). Opt-in and costs nothing for every `Item` that doesn't set it; `$context` is only
 * ever populated by a caller that resolves its slot with one (`UserMenu` does; `Community\
 * Navigation`/the dock don't, so this is simply never checked for those).
 *
 * `$surface` ('menu' or 'dock') isn't part of `$data` -- `$data` is static, author-declared
 * config the registering extension controls, but which markup shape an entry renders as is a
 * render-time layout decision the extension has no business making. Every entry always renders
 * into both surfaces (nothing is ever selected out) -- `Community\Navigation` just resolves the
 * slot twice, once per surface, passing `surface` as a plain Blade attribute alongside `:data`
 * each time. Not named `variant` (implies picking one) or `as` (collides with `Ux::add()
 * ->as()`, an unrelated concept -- an entry's stable id).
 */
class Item extends Component
{
    public function __construct(
        public array $data,
        public string $surface = 'menu',
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $hideOnPortal = $this->data['hideOnPortal'] ?? null;

        return view('kopling-core::portal.navigation.item', [
            'label' => $this->data['label'],
            'route' => $this->data['route'],
            'icon' => $this->data['icon'] ?? null,
            'surface' => $this->surface,
            'hidden' => $hideOnPortal !== null && $this->context?->isPortal($hideOnPortal) === true,
        ]);
    }
}
