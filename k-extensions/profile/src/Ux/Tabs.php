<?php

declare(strict_types=1);

namespace Kopling\Profile\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Kopling\Core\Ux\SlotResolver;
use Kopling\Core\Ux\UxEntry;

/**
 * The profile page's own tabbed region -- generic infrastructure, same category as
 * `Compose\Modes`/`Community\Sidebar`: profile owns the slot/resolver shape and registers its
 * own "Moments" entry into it (see `Extension::ux()`), any other extension with person-authored
 * content of its own (discussions' replies, say) registers a sibling tab the same way, instead
 * of this page ever reaching into that extension's model directly.
 */
class Tabs extends Component
{
    public const SLOT = 'kopling-profile::profile.tabs';

    /**
     * @var Collection<int, UxEntry>
     */
    public Collection $entries;

    public function __construct(Manager $manager, Context $context)
    {
        $this->entries = SlotResolver::resolve(self::SLOT, $manager->ux(), $context);
    }

    public function render(): View
    {
        return view('kopling-profile::ux.tabs');
    }
}
