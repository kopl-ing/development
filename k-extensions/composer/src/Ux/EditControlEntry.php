<?php

declare(strict_types=1);

namespace Kopling\Composer\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

/**
 * The "Edit" trigger on a moment's own control menu -- author-only, same `isOwnContent` shape
 * `ReportControlEntry` already uses (there's no Policy mechanism in this codebase to hang an
 * ability on instead, see decisions.md), just inverted: only the author sees this one, rather
 * than everyone but the author.
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
        $moment = $this->context?->getSubject();

        return view('kopling-composer::ux.edit-control-entry', [
            'moment' => $moment,
            'isOwnContent' => $moment && ($this->context?->isActor($moment->person) ?? false),
        ]);
    }
}
