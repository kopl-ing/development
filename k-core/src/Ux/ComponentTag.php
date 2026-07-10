<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * `Ux::add()`/`replace()` accept either an already-valid Blade tag string
 * ("k::portal.navigation.item") or a component's own class ("Item::class") -- `<x-dynamic-
 * component>` only ever accepts the former (it compiles straight to `<x-{{ $component }}>`),
 * so a class reference has to be resolved back into its tag before it's stored on a UxEntry.
 * Reverses whichever `Blade::componentNamespace()` registration the class falls under -- the
 * same transform `Illuminate\View\Compilers\ComponentTagCompiler::formatClassName()` applies
 * going the other way (PascalCase namespace segments <-> kebab-case, dot-joined tag
 * segments) -- so this works for any registered namespace, not just core's own `k::`.
 */
class ComponentTag
{
    public static function resolve(string $component): string
    {
        if (! class_exists($component)) {
            return $component;
        }

        foreach (Blade::getClassComponentNamespaces() as $alias => $namespace) {
            if (! str_starts_with($component, $namespace.'\\')) {
                continue;
            }

            $tag = collect(explode('\\', Str::after($component, $namespace.'\\')))
                ->map(fn (string $segment) => Str::kebab($segment))
                ->implode('.');

            return $alias.'::'.$tag;
        }

        throw new \InvalidArgumentException(
            "No Blade component namespace is registered for [{$component}] -- register one via ".
            'Blade::componentNamespace() before referencing it from Ux::add()/replace().'
        );
    }
}
