<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\LoadOrder;

use Kopling\Core\Extension\AbstractExtension;

/**
 * Orders `Manager::extensions()`'s raw map to respect every `LoadsAfter`/`LoadsBefore`/
 * `InfluencesLoadOrder` constraint. Composer's discovery order carries no meaning -- ties resolve
 * alphabetically. `kopling/core` is pinned first unconditionally, never entering the graph.
 */
class Resolver
{
    /**
     * @param  array<string, AbstractExtension>  $extensions
     * @return array<string, AbstractExtension>
     */
    public static function resolve(array $extensions): array
    {
        $core = $extensions['kopling/core'] ?? null;
        unset($extensions['kopling/core']);

        ksort($extensions);

        $sorted = static::sort(array_keys($extensions), static::edges($extensions));

        $result = $core !== null ? ['kopling/core' => $core] : [];

        foreach ($sorted as $package) {
            $result[$package] = $extensions[$package];
        }

        return $result;
    }

    /**
     * Builds a package => "packages it must load after" adjacency list. `LoadsAfter`/
     * `LoadsBefore` are collected first; `InfluencesLoadOrder` rules then only apply where the
     * matched extension has no explicit opinion already -- explicit always wins over inferred.
     *
     * @param  array<string, AbstractExtension>  $extensions
     * @return array<string, array<string>>
     */
    protected static function edges(array $extensions): array
    {
        $after = [];
        $explicit = [];

        foreach ($extensions as $package => $extension) {
            if ($extension instanceof LoadsAfter) {
                foreach ($extension->loadAfter() as $other) {
                    if (! isset($extensions[$other])) {
                        continue;
                    }

                    $after[$package][] = $other;
                    $explicit[$package][$other] = true;
                }
            }

            if ($extension instanceof LoadsBefore) {
                foreach ($extension->loadBefore() as $other) {
                    if (! isset($extensions[$other])) {
                        continue;
                    }

                    $after[$other][] = $package;
                    $explicit[$package][$other] = true;
                }
            }
        }

        foreach ($extensions as $package => $extension) {
            if (! $extension instanceof InfluencesLoadOrder) {
                continue;
            }

            foreach ($extension->loadOrderRules() as $contract => $directive) {
                foreach ($extensions as $candidate => $instance) {
                    if ($candidate === $package || ! $instance instanceof $contract) {
                        continue;
                    }

                    if (isset($explicit[$candidate][$package])) {
                        continue;
                    }

                    match ($directive) {
                        Directive::After => $after[$candidate][] = $package,
                        Directive::Before => $after[$package][] = $candidate,
                    };
                }
            }
        }

        return $after;
    }

    /**
     * Kahn's algorithm, tie-broken alphabetically since `$nodes` arrives alphabetical and each
     * pass picks the first still-unblocked node.
     *
     * @param  array<string>  $nodes
     * @param  array<string, array<string>>  $after  package => packages it must load after
     * @return array<string>
     */
    protected static function sort(array $nodes, array $after): array
    {
        $remaining = $nodes;
        $placed = [];

        while ($remaining !== []) {
            $ready = null;

            foreach ($remaining as $package) {
                if (array_diff($after[$package] ?? [], $placed) === []) {
                    $ready = $package;
                    break;
                }
            }

            if ($ready === null) {
                throw new \LogicException(
                    'Extension load order has a cycle involving: '.implode(', ', $remaining)
                );
            }

            $placed[] = $ready;
            $remaining = array_values(array_diff($remaining, [$ready]));
        }

        return $placed;
    }
}
