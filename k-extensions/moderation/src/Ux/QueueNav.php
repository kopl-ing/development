<?php

declare(strict_types=1);

namespace Kopling\Moderation\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;
use Kopling\Moderation\Flag;

/**
 * Chrome's generic `moderation.sidebar-panel` slot entry -- the four status filters used to live
 * as a `tabs-box` strip above the queue itself (plain `<a href>`, no htmx, so every click was a
 * full page reload); moved into the sidebar instead, same "one entry into a generic slot" pattern
 * `kopling-docs::ux.sidebar` already uses for its own portal.
 */
class QueueNav extends Component
{
    public function __construct(
        protected Request $request,
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-moderation::ux.queue-nav', [
            'status' => $this->request->query('status', Flag::STATUS_PENDING),
        ]);
    }
}
