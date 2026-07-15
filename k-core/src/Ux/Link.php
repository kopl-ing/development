<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A plain button-styled link -- the common case for a topbar entry (login/register, the admin
 * panel link, ...) the same way `Portal\Navigation\Item` is the common case for a side-nav
 * entry. Anything richer ships its own component instead and registers that FQCN with
 * `Ux::add()`; this one is a convenience, not a requirement. Was `AuthEmailPassword\AuthLink`
 * until the Admin extension needed the identical shape for its own topbar link -- promoted here
 * rather than duplicated a second time, or having Admin depend on an unrelated, optional
 * extension's component.
 */
class Link extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.link', [
            'label' => $this->data['label'],
            'route' => $this->data['route'],
            'variant' => $this->data['variant'] ?? 'ghost',
        ]);
    }
}
