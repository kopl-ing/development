<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal\Navigation;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The common case for a side-navigation entry -- just a link. Anything richer (a badge
 * count, custom markup) ships its own component instead and registers that FQCN with
 * Ux::add() -- this one is a convenience, not a requirement.
 */
class Item extends Component
{
    public function __construct(public array $data)
    {
    }

    public function render(): View
    {
        return view('core::portal.navigation.item', [
            'label' => $this->data['label'],
            'route' => $this->data['route'],
            'icon' => $this->data['icon'] ?? null,
        ]);
    }
}
