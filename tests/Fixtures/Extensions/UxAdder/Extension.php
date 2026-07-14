<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\UxAdder;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;

/**
 * Declares three entries: "widget" (always visible, a target for UxReplacer to replace),
 * "gadget" (gated behind a local permission, a target for UxRemover to remove), and "foreign"
 * (gated behind another extension's already-qualified permission id, proving Manager doesn't
 * re-prefix it -- see decisions.md, 2026-07-15). All three use plain string component
 * references (not a real Blade class) so these tests never need Blade/the container booted --
 * `ComponentTag::resolve()` short-circuits for a string that isn't an existing class.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Ux Adder Fixture';
    }

    public static function description(): string
    {
        return 'Adds entries for testing ChangesUx add()/replace()/remove().';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add('fixture::widget')
            ->in('fixture::slot')
            ->as('widget')
            ->add('fixture::gadget')
            ->in('fixture::slot')
            ->as('gadget')
            ->when('view-gadget')
            ->add('fixture::foreign')
            ->in('fixture::slot')
            ->as('foreign')
            ->when('kopling-core::guest');
    }
}
