<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;

/**
 * The one navbar/sidebar/main/rail chrome shell every portal now shares -- Community's own
 * page (the feed) is still its first, default caller, but Admin and Style Guide's own layouts
 * reuse this exact same component instead of each hand-rolling their own near-identical
 * topbar/sidebar/rail markup, which had quietly drifted into three different widths/behaviors
 * (see decisions.md) despite starting as copies of each other.
 *
 * Every constructor param defaults to Community's own values, so `<x-k::community.chrome />`
 * with no arguments (the feed's own call) behaves exactly as before. `$portalId` resolves which
 * Portal's `label` the topbar shows; `$topbarSlot`/`$sidebarSlot` are `Ux::add()`-targetable slot
 * names (see their own render usage below); `$railSlot`/`$composerSlot` are nullable -- `null`
 * skips that whole aside/footer outright, since a rail and a reply composer are Community-feed
 * concepts, not something every portal necessarily wants (Admin/Style Guide's own layouts pass
 * `null` for `$composerSlot`, and turn `$mobileDock` off, while still keeping a real (if
 * currently empty) rail aside for structural consistency). `$mainClass` is `Community`'s own
 * "narrow, centered reading column" choice for the feed -- Admin/Style Guide's own wider content
 * (tables, forms, component showcases) passes its own wider/unconstrained class instead.
 *
 * `$sidebarSlot`'s own entries are never wrapped in a `<ul>` here (unlike `$topbarSlot`'s flex
 * row) -- `Community\Sidebar`'s own entries render free-form `<div class="card">` blocks, which
 * would be invalid HTML inside a `<ul>`; `Community\Navigation` (reused, with its own `$data
 * ['slot']` override -- see its own docblock) self-wraps its own `<ul class="menu">` instead, as
 * one of potentially several entries in this same generic slot.
 */
class Chrome extends Component
{
    public Portal $portal;

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
    }

    public function render(): View
    {
        return view('kopling-core::community.chrome');
    }

    /**
     * Community's own two sidebar-panel entries -- `Navigation` (nav links, default slot: its
     * own `Navigation::SLOT`) and `Sidebar` (supplementary widgets) -- registered here rather
     * than hardcoded directly into `chrome.blade.php`, so the sidebar area is genuinely the same
     * generic `Ux::add()`-driven slot Admin/Style Guide's own layouts populate differently, not a
     * Community-only special case baked into the shared view.
     */
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
