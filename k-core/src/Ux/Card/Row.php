<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A generic horizontal arrangement of whatever's placed inside -- used to group components
 * inside `Footer` (or `Top`) into a row, same as `Column` groups them vertically. No props:
 * it's a layout primitive, not something with content-specific behavior of its own.
 */
class Row extends Component
{
    public function render(): View
    {
        return view('core::card.row');
    }
}
