<?php

declare(strict_types=1);

namespace Kopling\Reactions;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Ux;

class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Reactions';
    }

    public static function description(): string
    {
        return 'Lightweight emoji reactions for moments.';
    }

    /**
     * Fills the `core::card.footer` slot that `Card\Footer` deliberately leaves empty for a
     * real reactions feature. Registers the extension's own anonymous component by its tag
     * (`kopling-reactions::rail`), not a class -- extensions get an auto view namespace but
     * not a class-component namespace, so `ComponentTag` passes the tag through untouched and
     * the footer renders it via `<x-dynamic-component>`.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-reactions::rail')
            ->in('core::card.footer')
            ->as('rail');
    }
}
