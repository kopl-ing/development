<?php

namespace Kopling\Core\Ux\Card;

use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

class Accreditation extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render()
    {
        return view('kopling-core::card.accreditation', [
            'context' => $this->context,
        ]);
    }
}