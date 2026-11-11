<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\AuthLayoutEntry;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Link;
use Kopling\Core\Ux\Portal\AuthLayout;

class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Auth Layout Entry Fixture';
    }

    public static function description(): string
    {
        return 'Adds one entry to each slot of the auth layout.';
    }

    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(Link::class, ['label' => 'Fixture Header', 'route' => 'kopling-core::community/community'])
            ->in(AuthLayout::HEADER)
            ->as('header')
            ->add(Link::class, ['label' => 'Fixture Aside', 'route' => 'kopling-core::community/community'])
            ->in(AuthLayout::ASIDE)
            ->as('aside')
            ->add(Link::class, ['label' => 'Fixture Footer', 'route' => 'kopling-core::community/community'])
            ->in(AuthLayout::FOOTER)
            ->as('footer');
    }
}
