<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Turns the flat, unordered result of `Manager::ux()` into what one slot should actually
 * render: just its entries, positioned by `after`/`before`, with anything the current person
 * can't see already filtered out. An `after`/`before` referencing a missing entry (its
 * owning extension got removed, or it was simply never registered) is ignored rather than
 * an error -- outlets compose, they never break each other.
 */
class SlotResolver
{
    /**
     * @param  Collection<int, UxEntry>  $entries
     * @return Collection<int, UxEntry>
     */
    public static function resolve(string $slot, Collection $entries): Collection
    {
        $ordered = static::order(
            $entries->filter(fn (UxEntry $entry) => $entry->slot === $slot)->values()->all()
        );

        return collect($ordered)->filter(fn (UxEntry $entry) => static::passes($entry))->values();
    }

    /**
     * @param  array<UxEntry>  $entries
     * @return array<UxEntry>
     */
    protected static function order(array $entries): array
    {
        foreach ($entries as $entry) {
            if ($entry->after !== null) {
                $entries = static::moveAfter($entries, $entry, $entry->after);
            }

            if ($entry->before !== null) {
                $entries = static::moveBefore($entries, $entry, $entry->before);
            }
        }

        return $entries;
    }

    /**
     * @param  array<UxEntry>  $entries
     * @return array<UxEntry>
     */
    protected static function moveAfter(array $entries, UxEntry $entry, string $anchorId): array
    {
        $anchorIndex = static::indexOf($entries, $anchorId);

        if ($anchorIndex === null) {
            return $entries;
        }

        $entries = array_values(array_filter($entries, fn (UxEntry $candidate) => $candidate !== $entry));
        $anchorIndex = static::indexOf($entries, $anchorId);

        array_splice($entries, $anchorIndex + 1, 0, [$entry]);

        return $entries;
    }

    /**
     * @param  array<UxEntry>  $entries
     * @return array<UxEntry>
     */
    protected static function moveBefore(array $entries, UxEntry $entry, string $anchorId): array
    {
        $anchorIndex = static::indexOf($entries, $anchorId);

        if ($anchorIndex === null) {
            return $entries;
        }

        $entries = array_values(array_filter($entries, fn (UxEntry $candidate) => $candidate !== $entry));
        $anchorIndex = static::indexOf($entries, $anchorId);

        array_splice($entries, $anchorIndex, 0, [$entry]);

        return $entries;
    }

    /**
     * @param  array<UxEntry>  $entries
     */
    protected static function indexOf(array $entries, string $id): ?int
    {
        foreach ($entries as $index => $entry) {
            if ($entry->id === $id) {
                return $index;
            }
        }

        return null;
    }

    protected static function passes(UxEntry $entry): bool
    {
        return match (true) {
            $entry->condition === null => true,
            $entry->condition instanceof \Closure => (bool) ($entry->condition)(Auth::user()),
            default => Gate::allows($entry->condition),
        };
    }
}
