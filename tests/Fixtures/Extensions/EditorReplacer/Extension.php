<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\EditorReplacer;

use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\Card\Row;

/**
 * Replaces Core's own `notion` entry in `Editor::SLOT` with `Card\Row` -- already a real,
 * renderable, prop-less Core component (same reasoning `CardControlEntry` reuses `Row` instead
 * of needing a whole fixture view of its own) -- proving the v2 "swap the whole editor
 * implementation" path already works today via the ordinary `Ux::replace()` mechanism, even
 * though no second real editor implementation exists yet.
 */
class Extension extends AbstractExtension implements ChangesUx
{
    public static function name(): string
    {
        return 'Editor Replacer Fixture';
    }

    public static function description(): string
    {
        return 'Replaces the notion editor entry in Editor::SLOT, for testing ChangesUx replace() on it.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->replace('kopling-core::notion', Row::class);
    }
}
