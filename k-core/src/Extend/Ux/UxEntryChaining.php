<?php

declare(strict_types=1);

namespace Kopling\Core\Extend\Ux;

/**
 * Shared by `UxAdding` and `UxEditing` -- both configure a live, `Add`-actioned `UxEntry` that
 * `Manager::ux()` registers verbatim, so `first`/`flush` actually take effect. `UxReplacing`
 * doesn't extend this: `AggregatesUx::applyUxReplace()` never copies those two fields onto the
 * target it's replacing, so setting them there would silently do nothing.
 */
abstract class UxEntryChaining extends UxChaining
{
    /** Pins this entry to the very front of its slot -- see `UxEntry::$first`. */
    public function first(): static
    {
        $this->entry->first = true;

        return $this;
    }

    /** Marks this entry edge-to-edge -- see `UxEntry::$flush`. */
    public function flush(): static
    {
        $this->entry->flush = true;

        return $this;
    }
}
