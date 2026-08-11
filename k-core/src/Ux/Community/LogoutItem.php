<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The account dropdown's own "log out" entry -- a POST form rather than `Portal\Navigation\Item`,
 * since logout is never a plain GET link.
 */
class LogoutItem extends Component
{
    public function render(): View
    {
        return view('kopling-core::community.logout-item');
    }
}
