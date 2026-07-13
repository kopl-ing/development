<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal\Navigation;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The common case for a side-navigation entry -- just a link. Anything richer (a badge
 * count, custom markup) ships its own component instead and registers that FQCN with
 * Ux::add() -- this one is a convenience, not a requirement.
 *
 * `$data['icon']`, when given, is raw inline `<svg>` markup (echoed unescaped) -- matching every
 * other icon already in this codebase (theme-switcher, card controls, reply-dock, thread-title),
 * none of which use an icon font/class. Trusted, author-declared config, not user input, so
 * unescaped output is safe the same way any other `$data` value here is.
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
    public function __construct(public array $data, public string $surface = 'menu')
    {
    }

    public function render(): View
    {
        return view('kopling-core::portal.navigation.item', [
            'label' => $this->data['label'],
            'route' => $this->data['route'],
            'icon' => $this->data['icon'] ?? null,
            'surface' => $this->surface,
        ]);
    }
}
