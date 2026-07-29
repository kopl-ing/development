<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

class Author extends Component
{
    public ?string $url = null;
    public ?string $name = null;

    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
        $this->url = $context->getSubjectUrl();
        $this->name = $context->getSubject()?->name;
    }

    public function render(): View
    {
        return view('kopling-core::card.author', [
            'name' => $this->name,
            'url' => $this->url,
        ]);
    }
}
