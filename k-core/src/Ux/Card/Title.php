<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * A Moment card's own title, in `Top` rather than `Body` -- `flex-1` in its own view grows it to
 * fill the header row, pushing avatar/author/timestamp/control to the row's right edge.
 */
class Title extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-core::card.title', [
            'title' => $this->context?->getSubject()?->title,
            'url' => $this->context?->getSubjectUrl(),
        ]);
    }
}
