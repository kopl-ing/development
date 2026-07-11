<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Tag extends Component
{
    public function __construct(public string $label)
    {
    }

    public function render(): View
    {
        return view('kopling-core::card.tag');
    }
}
