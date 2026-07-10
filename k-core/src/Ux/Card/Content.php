<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * `Body`'s default child -- the moment's own title and text. Reads `$context->subject`
 * directly, same as every other slot-rendered leaf in this domain.
 */
class Content extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('core::card.content', [
            'title' => $this->context?->subject?->title,
            'body' => $this->context?->subject?->body,
        ]);
    }
}
