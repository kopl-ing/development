<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal\Navigation;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * The common case for a side-navigation entry -- just a link. Anything richer ships its own
 * component and registers that FQCN instead. `$data['hideOnPortal']`, a Portal id, hides this
 * entry while `$context->isPortal()` says that's the portal currently being viewed. `$surface`
 * ('menu'/'dock') isn't part of `$data` -- it's a render-time layout decision, not something the
 * registering extension controls.
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
