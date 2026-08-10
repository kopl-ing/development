<?php

declare(strict_types=1);

namespace Kopling\Core\Extend\Ux;

/** Returned by `Ux::add()`. */
class UxAdding extends UxEntryChaining
{
    public function as(string $id): static
    {
        $this->entry->id = $id;

        return $this;
    }
}
