<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Support\Collection;

/**
 * The fluent builder returned by `Kopling\Core\Extension\Contract\ChangesUx::ux()` --
 * mirrors Laravel's own `Route::get()->name()->middleware()` chaining. `add()` starts a new
 * UxEntry; every other method mutates the entry started by the most recent `add()` and
 * returns `$this`, so several entries can be declared in one chained call.
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

    public function as(string $id): static
    {
        $this->current->id = $id;

        return $this;
    }

    public function when(string|\Closure $condition): static
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
