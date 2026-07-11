<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;

/**
 * The Community portal's own chrome (topbar/sidebar/rail/composer) split out of
 * `layouts/community.blade.php` so a page that isn't the feed itself -- discussions' show page,
 * a future tags page, anything that still wants to sit inside the same site experience -- can
 * wrap its own content in this without also inheriting the feed's `Context`/pagination
 * requirement. Resolves the Community portal itself via `Manager`, the same way `Sidebar`
 * already resolves its own slot entries independently, rather than asking every caller to look
 * it up and pass it in -- a page rendering this has no reason to already have a `Portal`
 * instance on hand (`InjectPortal` only resolves one for routes registered under a Portal's own
 * route group, which a page like discussions' isn't).
 */
class Chrome extends Component
{
    public Portal $portal;

    public function __construct(Manager $manager)
    {
        $this->portal = $manager->portals()->firstWhere('id', 'kopling-core::community');
    }

    public function render(): View
    {
        return view('kopling-core::community.chrome');
    }
}
