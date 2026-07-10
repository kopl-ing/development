<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * The card's own action button -- floats to the far right of Top via its own `ml-auto`, not
 * something Top imposes. Purely presentational for now: no real per-moment actions (edit,
 * delete, report) exist yet, so this isn't wired into a dropdown menu (see daisyUI's own
 * `dropdown` component for that, once there's something real to put in it) or any htmx
 * attribute -- just the button a real menu will eventually hang off of. Takes `$data`/
 * `$context` only for the same predictable shape every slot-rendered leaf has; neither is
 * used yet.
 */
class Control extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('core::card.control');
    }
}
