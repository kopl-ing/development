<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Card;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * A Moment card's own title, in `Top` rather than `Body` -- registered first among `Top`'s
 * default entries (see `Top::defaults()`) and given `flex-1` in its own view so it grows to
 * fill the header row, pushing whichever entries follow it (avatar, author, timestamp,
 * control) to the row's right edge without any of those generic, reused-elsewhere leaves
 * needing a layout opinion baked into them. Below `sm:`, `basis-full` (paired with `Top`'s own
 * `flex-wrap`) instead drops it onto its own line -- a real headline and a byline's worth of
 * avatar/author/timestamp/control don't fit one row at that width without either wrapping
 * unpredictably or everything shrinking past readability.
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
