<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

class Timestamp extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-core::card.timestamp', [
            'value' => $this->context?->getSubject()?->created_at?->diffForHumans(),
        ]);
    }
}
