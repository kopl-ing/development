<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Ux\UxAction;
use Kopling\Core\Ux\UxEntry;

trait AggregatesUx
{
    /**
     * Resolves every extension's `Add`/`Replace`/`Remove` operations, in `extensions()` order --
     * `Replace`/`Remove` targeting an entry not yet registered (wrong order, or never existed) is
     * a no-op, so an extension can only replace/remove something an earlier one already added.
     *
     * @return Collection<int, UxEntry>
     */
    public function ux(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['ux'])->map(fn (array $data) => UxEntry::fromArray($data));
        }

        $registry = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesUx) {
                continue;
            }

            $prefix = $this->id($package).'::';

            foreach ($extension->ux()->entries() as $entry) {
                match ($entry->action) {
                    UxAction::Add => $this->applyUxAdd($registry, $entry, $prefix),
                    UxAction::Replace => $this->applyUxReplace($registry, $entry),
                    UxAction::Remove => $this->applyUxRemove($registry, $entry),
                };
            }
        }

        return collect(array_values($registry));
    }

    /**
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxAdd(array &$registry, UxEntry $entry, string $prefix): void
    {
        $entry->id = $prefix.$entry->id;

        if (is_string($entry->condition) && ! str_contains($entry->condition, '::')) {
            $entry->condition = $prefix.$entry->condition;
        }

        // Two extensions can never collide here (the prefix is always the owning extension's own
        // id) -- a collision means the same extension reused an ->as() name across two add()
        // calls, which would otherwise silently overwrite the first entry's slot/component/data.
        if (isset($registry[$entry->id]) && $registry[$entry->id]->action === UxAction::Add) {
            throw new \LogicException(sprintf(
                'Two Ux::add() entries both resolve to id "%s" -- give one a distinct ->as() name. '
                .'A second add() silently overwrites the first (including its own slot), it never merges with it.',
                $entry->id
            ));
        }

        $registry[$entry->id] = $entry;
    }

    /**
     * Mutates the target in place to keep its original position. Only `component`/`data` are
     * always overwritten; `slot`/`after`/`before`/`condition` only if this entry actually set them.
     *
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxReplace(array &$registry, UxEntry $entry): void
    {
        $target = $registry[$entry->id] ?? null;

        if ($target === null) {
            return;
        }

        $target->component = $entry->component;
        $target->data = $entry->data;

        foreach (['slot', 'after', 'before', 'condition'] as $field) {
            if ($entry->{$field} !== null) {
                $target->{$field} = $entry->{$field};
            }
        }
    }

    /**
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxRemove(array &$registry, UxEntry $entry): void
    {
        unset($registry[$entry->id]);
    }
}
