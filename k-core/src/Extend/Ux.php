<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

use Illuminate\Support\Collection;
use Kopling\Core\Ux\UxAction;
use Kopling\Core\Ux\UxEntry;

/**
 * The fluent builder returned by `ChangesUx::ux()`. `add()`/`replace()`/`remove()` each start a
 * new `UxEntry`; every other method mutates whichever entry is currently selected.
 */
class Ux
{
    /**
     * @var array<UxEntry>
     */
    protected array $entries = [];

    protected ?UxEntry $current = null;

    public static function make(): static
    {
        return new static();
    }

    public function add(string $component, array $data = []): static
    {
        $this->entries[] = $this->current = new UxEntry($component, $data);

        return $this;
    }

    /**
     * `$id` is the target's already fully-qualified id. A missing target is a no-op.
     */
    public function replace(string $id, string $component, array $data = []): static
    {
        $entry = new UxEntry($component, $data);
        $entry->id = $id;
        $entry->action = UxAction::Replace;

        $this->entries[] = $this->current = $entry;

        return $this;
    }

    public function remove(string $id): static
    {
        $entry = new UxEntry('');
        $entry->id = $id;
        $entry->action = UxAction::Remove;

        $this->entries[] = $this->current = $entry;

        return $this;
    }

    /**
     * Re-selects an entry added earlier in this same chain so it can be configured further.
     * Only searches this same `Ux` instance -- unlike `replace()`, not for another extension's.
     *
     * @throws \InvalidArgumentException if no entry with this id was added earlier in this chain
     */
    public function edit(string $id): static
    {
        for ($i = count($this->entries) - 1; $i >= 0; $i--) {
            if ($this->entries[$i]->id === $id) {
                $this->current = $this->entries[$i];

                return $this;
            }
        }

        throw new \InvalidArgumentException("No entry with id [{$id}] was added earlier in this Ux chain.");
    }

    public function in(string $slot): static
    {
        $this->current->slot = $slot;

        return $this;
    }

    public function after(string $id): static
    {
        $this->current->after = $id;

        return $this;
    }

    public function before(string $id): static
    {
        $this->current->before = $id;

        return $this;
    }

    /** Pins this entry to the very front of its slot -- see `UxEntry::$first`. */
    public function first(): static
    {
        $this->current->first = true;

        return $this;
    }

    /** Marks this entry edge-to-edge -- see `UxEntry::$flush`. */
    public function flush(): static
    {
        $this->current->flush = true;

        return $this;
    }

    public function as(string $id): static
    {
        $this->current->id = $id;

        return $this;
    }

    public function when(string $condition): static
    {
        $this->current->condition = $condition;

        return $this;
    }

    /**
     * @return Collection<int, UxEntry>
     */
    public function entries(): Collection
    {
        return collect($this->entries);
    }
}
