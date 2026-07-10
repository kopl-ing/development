<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

/**
 * One piece of UI an extension (or core) places into a named slot -- e.g. a link in
 * `core::side-navigation`. Unlike `Permission`/`Portal`/`StorageRequest`, deliberately not
 * readonly: `Ux::add()` returns an entry that `in()`/`after()`/`before()`/`as()`/`when()`
 * mutate incrementally as the fluent chain continues.
 *
 * `$id` defaults to `$component` if `as()` is never called -- fine unless another entry
 * needs to anchor `after()`/`before()` it, in which case the author should give it a stable
 * name. `$slot` is a fully-qualified string the author writes out in full (e.g.
 * "core::side-navigation") and is never auto-prefixed by Manager -- it names a shared
 * rendezvous point other extensions must be able to reference exactly, unlike a Permission
 * id which is private to its own Gate check. `$after`/`$before` reference another entry's
 * id within the same slot; a reference to a missing/uninstalled entry is ignored, never an
 * error (see SlotResolver). `$condition` is `null` (always visible), a local permission id
 * (a string, prefixed by Manager the same way `Permission::$id` is, then checked via Gate),
 * or a closure `fn (Person $person): bool` for anything a permission can't express.
 */
class UxEntry
{
    public string $id;

    public ?string $slot = null;

    public ?string $after = null;

    public ?string $before = null;

    /**
     * @var string|(\Closure(?\Kopling\Core\People\Person): bool)|null
     */
    public string|\Closure|null $condition = null;

    /**
     * @param  string  $component  A Blade component reference (e.g. "k::portal.navigation.item"),
     *                              rendered via <x-dynamic-component>.
     * @param  array  $data  Passed whole as the single `data` prop -- every component a UxEntry
     *                        can render (core-provided or an extension's own) takes one `array
     *                        $data` constructor param, not a spread of named props, so any
     *                        component can be targeted without Manager/SlotResolver needing to
     *                        know its individual prop names.
     */
    public function __construct(
        public readonly string $component,
        public readonly array $data = [],
    ) {
        $this->id = $component;
    }
}
