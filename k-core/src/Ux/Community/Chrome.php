<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Settings\Settings;

/**
 * The one navbar/sidebar/main/rail chrome shell Community, Admin, and Style Guide all share.
 * Every constructor param defaults to Community's own values. `$railSlot`/`$composerSlot` are
 * nullable -- `null` skips that whole aside/footer for portals that don't want a feed rail or
 * reply composer. `$label`/`$logo` substitute the admin-configured community name/logo for
 * `$portal->label`, but only when `$portalId` is actually `kopling-core::community`.
 */
class Chrome extends Component
{
    public Portal $portal;

    public string $label;

    public ?string $logo;

    public function __construct(
        Manager $manager,
        public string $portalId = 'kopling-core::community',
        public string $topbarSlot = 'kopling-core::community.topbar',
        public string $sidebarSlot = 'kopling-core::community.sidebar-panel',
        public ?string $railSlot = 'kopling-core::community.rail',
        public ?string $composerSlot = 'kopling-core::community.composer',
        public bool $mobileDock = true,
        public string $mainClass = 'max-w-2xl mx-auto',
    ) {
        $this->portal = $manager->portals()->firstWhere('id', $this->portalId);

        $isCommunity = $this->portalId === 'kopling-core::community';

        $this->label = ($isCommunity ? Settings::get('kopling-core::community-name') : null)
            ?? $this->portal->label;
        $this->logo = $isCommunity ? Settings::get('kopling-core::community-logo') : null;
    }

    public function render(): View
    {
        return view('kopling-core::community.chrome');
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(Navigation::class)
            ->in('kopling-core::community.sidebar-panel')
            ->as('navigation')
            ->add(Sidebar::class)
            ->in('kopling-core::community.sidebar-panel')
            ->as('sidebar')
            ->after('navigation');
    }
}
