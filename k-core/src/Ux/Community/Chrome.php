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
 * requirement. Resolves the Community portal itself via `Manager` rather than trusting whatever
 * `InjectPortal` already resolved for the current request (available as the shared `$portal`
 * view variable, or `$request->attributes->get('portal')`) -- deliberately: this component's
 * whole purpose is Community's own chrome, specifically, so it hardcodes that target the same
 * way `Extension::extendsPortals()` targets a Portal by its fully-qualified id rather than an
 * ambient one. That both of today's call sites happen to already be grouped under Community
 * (discussions' routes now are too, via `ExtendsPortals`) is incidental, not the reason for this
 * -- a future page embedding Community's chrome from underneath some other Portal entirely would
 * still need Community's own instance here, not whatever that other Portal resolved to.
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
