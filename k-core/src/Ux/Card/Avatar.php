<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * A placeholder-initials avatar -- there's no real image/file upload behind this yet (see
 * Storage's still-unbuilt admin mapping), so this only ever renders initials, never an
 * `<img>`. Swapping in a real image once uploads exist replaces this component's view, not
 * anything that uses it. Reads the author's name off `$context->subject->person`.
 */
class Avatar extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $name = $this->context?->subject?->person?->name;

        return view('core::card.avatar', [
            'initials' => $this->initials($name),
            'name' => $name,
        ]);
    }

    protected function initials(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        $words = array_slice(explode(' ', $name), 0, 2);

        return strtoupper(implode('', array_map(fn (string $word) => $word[0], $words)));
    }
}
