<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

use Illuminate\Support\Collection;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extend\Ux\UxAdding;
use Kopling\Core\Extend\Ux\UxEditing;
use Kopling\Core\Extend\Ux\UxReplacing;
use Kopling\Core\Ux\UxAction;
use Kopling\Core\Ux\UxEntry;

/**
 * The fluent builder returned by `ChangesUx::ux()`. `add()`/`edit()`/`replace()` each return a
 * staged object (`UxAdding`/`UxEditing`/`UxReplacing`) exposing only the modifiers that stage
 * actually supports -- see those classes for which and why. `remove()` takes no modifiers, so it
 * returns straight back here to start the next entry.
 */
class Ux implements ProvidesUxEntries
{
    /**
     * @var array<UxEntry>
     */
    protected array $entries = [];

    public static function make(): static
    {
        return new static();
    }

    public function add(string $component, array $data = []): UxAdding
    {
        $this->entries[] = $entry = new UxEntry($component, $data);

        return new UxAdding($this, $entry);
    }

    /**
     * `$id` is the target's already fully-qualified id. A missing target is a no-op.
     */
    public function replace(string $id, string $component, array $data = []): UxReplacing
    {
        $entry = new UxEntry($component, $data);
        $entry->id = $id;
        $entry->action = UxAction::Replace;

        $this->entries[] = $entry;

        return new UxReplacing($this, $entry);
    }

    public function remove(string $id): static
    {
        $entry = new UxEntry('');
        $entry->id = $id;
        $entry->action = UxAction::Remove;

        $this->entries[] = $entry;

        return $this;
    }

    /**
     * Re-selects an entry added earlier in this same chain so it can be configured further.
     * Only searches this same `Ux` instance -- unlike `replace()`, not for another extension's.
     *
     * @throws \InvalidArgumentException if no entry with this id was added earlier in this chain
     */
    public function edit(string $id): UxEditing
    {
        for ($i = count($this->entries) - 1; $i >= 0; $i--) {
            if ($this->entries[$i]->id === $id) {
                return new UxEditing($this, $this->entries[$i]);
            }
        }

        throw new \InvalidArgumentException("No entry with id [{$id}] was added earlier in this Ux chain.");
    }

    /**
     * @return Collection<int, UxEntry>
     */
    public function entries(): Collection
    {
        return collect($this->entries);
    }
}
