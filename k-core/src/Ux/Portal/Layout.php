<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;

/**
 * The one shell every Portal's layout renders through -- shared head/body chrome plus the
 * side-navigation region -- so layouts/community.blade.php and layouts/admin.blade.php stop
 * duplicating markup. Deliberately portal-agnostic beyond display (see Portal's own
 * docblock, charter D29: never a gating mechanism itself): every portal renders the same
 * `core::side-navigation` slot -- what shows up is decided entirely by each entry's own
 * permission, not by which Portal this is.
 */
class Layout extends Component
{
    public Portal $portal;

    public function __construct(Manager $manager, string $portal)
    {
        $this->portal = $manager->portals()->firstWhere('id', $portal);
    }

    public function render(): View
    {
        return view('core::portal.layout');
    }
}
