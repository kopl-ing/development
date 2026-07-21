<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

use Illuminate\Support\Collection;
use Kopling\Core\Ux\UxAction;
use Kopling\Core\Ux\UxEntry;

/**
 * The fluent builder returned by `Kopling\Core\Extension\Contract\ChangesUx::ux()` --
 * mirrors Laravel's own `Route::get()->name()->middleware()` chaining. `add()`/`replace()`/
 * `remove()` each start a new UxEntry; every other method (`in()`/`after()`/`before()`/
 * `as()`/`when()`) mutates whichever entry is currently selected and returns `$this`, so
 * several entries can be declared in one chained call. `edit()` re-selects an entry already
 * added earlier in this same chain, so you're never stuck only being able to configure
 * whichever one you called `add()` on most recently.
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
     * Overwrites an already-registered entry's component/data (and, if also chained here,
     * whichever of slot/after/before/when it's given too -- anything left unset keeps the
     * original entry's value). `$id` is the target's already fully-qualified id, same as
     * `after()`/`before()` -- your own entries' ids included, if you know your own extension's
     * prefix. A target that doesn't exist (removed, or never registered) is a no-op, same as
     * a dangling `after()`/`before()`.
     */
    public function replace(string $id, string $component, array $data = []): static
    {
        $entry = new UxEntry($component, $data);
        $entry->id = $id;
        $entry->action = UxAction::Replace;

        $this->entries[] = $this->current = $entry;

        return $this;
    }

    /**
     * Removes an already-registered entry outright. Same targeting rule as `replace()`: `$id`
     * is the target's fully-qualified id, and a missing target is a no-op.
     */
    public function remove(string $id): static
    {
        $entry = new UxEntry('');
        $entry->id = $id;
        $entry->action = UxAction::Remove;

        $this->entries[] = $this->current = $entry;

        return $this;
    }

    /**
     * Re-selects an entry added earlier in this same chain (by whatever id it currently has --
     * explicit via `as()`, or the default) so it can be configured further, instead of only
     * ever being able to continue chaining onto whichever `add()`/`replace()`/`remove()` call
     * came last. Only searches entries declared in this same `Ux` instance -- unlike
     * `replace()`, this isn't for reaching into another extension's entries, just for not
     * being stuck mid-chain.
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

    /**
     * Pins this entry to the very front of its slot -- see `UxEntry::$first` for why this
     * exists alongside `after()`/`before()` rather than an anchor id doing the same job.
     */
    public function first(): static
    {
        $this->current->first = true;

        return $this;
    }

    /**
     * Marks this entry as edge-to-edge -- see `UxEntry::$flush` for what that means and why
     * it only matters in `Card\Body`.
     */
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
