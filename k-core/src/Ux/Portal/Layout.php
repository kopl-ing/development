<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The one truly universal shell every Portal's layout renders through -- html/head/body
 * chrome, nothing else. Deliberately holds no region markup of its own (no header, no side
 * navigation): which regions exist, their slot names, and their arrangement is each Portal
 * layout's own decision (see layouts/community.blade.php vs. layouts/admin.blade.php) --
 * this component only owns what's genuinely identical across every one of them. `$portal`
 * doesn't need to be threaded through here: `PortalController` already binds it directly on
 * the top-level view (`view($portal->layout)->with('portal', $portal)`), so it's already in
 * scope for whichever layout renders this component's default slot.
 */
class Layout extends Component
{
    public function render(): View
    {
        return view('core::portal.layout');
    }
}
