<?php

declare(strict_types=1);

namespace Kopling\Widgets;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;

/**
 * Rail widgets for the community: a "pulse" (a few live counts) and, when the tags extension
 * is installed, a "popular tags" cloud. Registers into the (otherwise empty) right-rail slot
 * `kopling-core::community.rail`, which the chrome renders on wide screens across the feed and
 * discussion pages. Pure server-rendered daisyUI -- each widget self-hides when it has nothing
 * to show, and the tags one no-ops entirely without the tags extension.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Widgets';
    }

    public static function description(): string
    {
        return 'Community pulse and popular tags in the feed rail.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-widgets::pulse')
            ->in('kopling-core::community.rail')
            ->as('pulse')
            ->add('kopling-widgets::tags')
            ->in('kopling-core::community.rail')
            ->as('tags')
            ->after('pulse');
    }
}
