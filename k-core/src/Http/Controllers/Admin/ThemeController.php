<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;

/**
 * Placeholder only -- proves the Admin portal's routing/gate/layout chain resolves end to end.
 * Token storage, the ThemeValidator, and the live-preview editor are separate, later work.
 */
class ThemeController
{
    public function __invoke(): View
    {
        return view('core::admin.theme');
    }
}
