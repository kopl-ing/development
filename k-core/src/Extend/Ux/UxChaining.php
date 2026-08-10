<?php

declare(strict_types=1);

namespace Kopling\Core\Extend\Ux;

use Illuminate\Support\Collection;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Ux\UxEntry;

/**
 * Base of every staged object `Ux::add()`/`edit()`/`replace()` return -- holds the entry this
 * stage configures plus a back-reference to the owning `Ux` chain, so `add()`/`edit()`/
 * `replace()`/`remove()` can start the next entry without each concrete stage re-declaring them.
 */
abstract class UxChaining implements ProvidesUxEntries
{
    public function __construct(
        protected readonly Ux $ux,
        protected readonly UxEntry $entry,
    ) {}

    public function add(string $component, array $data = []): UxAdding
    {
        return $this->ux->add($component, $data);
    }

    public function edit(string $id): UxEditing
    {
        return $this->ux->edit($id);
    }

    public function replace(string $id, string $component, array $data = []): UxReplacing
    {
        return $this->ux->replace($id, $component, $data);
    }

    public function remove(string $id): Ux
    {
        return $this->ux->remove($id);
    }

    public function in(string $slot): static
    {
        $this->entry->slot = $slot;

        return $this;
    }

    public function after(string $id): static
    {
        $this->entry->after = $id;

        return $this;
    }

    public function before(string $id): static
    {
        $this->entry->before = $id;

        return $this;
    }

    public function when(string $condition): static
    {
        $this->entry->condition = $condition;

        return $this;
    }

    /**
     * @return Collection<int, UxEntry>
     */
    public function entries(): Collection
    {
        return $this->ux->entries();
    }
}
