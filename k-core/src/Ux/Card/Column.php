<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A generic vertical arrangement of whatever's placed inside -- same reasoning as `Row`,
 * just stacked instead of side by side.
 */
class Column extends Component
{
    public function render(): View
    {
        return view('kopling-core::card.column');
    }
}
