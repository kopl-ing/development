<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Community;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * The Community portal's primary nav links. Rendered twice per page -- `$surface = 'menu'` (desktop
 * sidebar) and `'dock'` (mobile bottom nav) -- passed through to each entry's own component as an
 * extra Blade attribute. `$data['slot']` overrides which slot gets resolved (`self::SLOT` when
 * omitted), letting Admin/Style Guide reuse this same component for their own nav slots.
 */
class Navigation extends Component
{
    public const SLOT = 'kopling-core::community.navigation';

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, public string $surface = 'menu', public array $data = [])
    {
        $this->entries = SlotResolver::resolve($data['slot'] ?? self::SLOT, $manager->ux());
    }

    public function render(): View
    {
        return view('kopling-core::community.navigation');
    }

    public static function defaults(Ux $ux): void
    {
        $ux->add(Item::class, [
            'label' => __('kopling-core::community.home'),
            'route' => 'kopling-core::community/community',
            'icon' => 'kopling-core::home',
        ])
            ->in(self::SLOT)
            ->as('home');
    }
}
