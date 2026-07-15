<?php

declare(strict_types=1);

namespace Kopling\ThreadTitle;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Portal\PortalExtension;

/**
 * "Toby's idea" — on a discussion page, once you scroll past the moment, its title eases into
 * the sticky topbar (centered, covering the normal header controls) so you always know which
 * thread you're in. A companion to the discussions extension (it doesn't modify it): a
 * topbar-slot component that renders only on a discussion page (the route has a bound Moment).
 * Inline Alpine scroll handler; styling ships as css/app.css (the overlay can't be expressed in
 * the utility classes core's compiled build happens to include).
 */
class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasIcons
{
    public static function name(): string
    {
        return 'Thread title';
    }

    public static function description(): string
    {
        return 'The moment title slides into the sticky topbar as you scroll a discussion.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-thread-title::sticky')
            ->in('kopling-core::community.topbar')
            ->as('thread-title');
    }

    /**
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'thread', label: 'Thread', default: 'fas-comment'),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->css(__DIR__.'/../css/app.css'),
        ];
    }
}
