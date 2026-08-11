<?php

declare(strict_types=1);

namespace Kopling\Discussions\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * The "Edit" trigger on a reply's own control menu -- author-only, same `isOwnContent` shape
 * `Kopling\Composer\Ux\EditControlEntry` already uses for a Moment.
 */
class EditControlEntry extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $reply = $this->context?->getSubject();

        return view('kopling-discussions::ux.edit-control-entry', [
            'reply' => $reply,
            'isOwnContent' => $reply && ($this->context?->isActor($reply->person) ?? false),
        ]);
    }
}
