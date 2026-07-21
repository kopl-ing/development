<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

/**
 * One piece of UI an extension (or core) places into a named slot -- e.g. a link in
 * `kopling-core::community.navigation`. Unlike `Permission`/`Portal`/`StorageRequest`, deliberately not
 * readonly: `Ux::add()` returns an entry that `in()`/`after()`/`before()`/`as()`/`when()`
 * mutate incrementally as the fluent chain continues, and `Manager::ux()` mutates `component`/
 * `data` (and whichever of `slot`/`after`/`before`/`condition` were set) in place on an
 * already-registered entry when a later `Ux::replace()` targets it.
 *
 * `$id` defaults to `$component` if `as()` is never called -- fine unless another entry
 * needs to anchor `after()`/`before()` it, in which case the author should give it a stable
 * name. For an `Add` entry this is the local part, auto-prefixed by Manager, same as
 * `Permission::$id`; for `Replace`/`Remove` it's instead the fully-qualified id of the entry
 * being targeted -- written out in full, the same as `$after`/`$before`, never prefixed.
 *
 * `$slot` is a fully-qualified string the author writes out in full (e.g.
 * "kopling-core::community.navigation") and is never auto-prefixed by Manager -- it names a shared
 * rendezvous point other extensions must be able to reference exactly, unlike a Permission
 * id which is private to its own Gate check. `$after`/`$before` reference another entry's
 * id within the same slot; a reference to a missing/uninstalled entry is ignored, never an
 * error (see SlotResolver). `$condition` is `null` (always visible), a local permission id (a
 * string, prefixed by Manager the same way `Permission::$id` is), or another extension's (or
 * Core's) already fully-qualified permission id (e.g. "kopling-core::guest") -- Manager tells
 * the two apart by whether it already contains "::", same convention `$after`/`$before` use,
 * and never re-prefixes the latter. Deliberately never a closure, so every entry stays plain
 * data an extension author can reason about without running it, and cacheable to a flatfile.
 */
class UxEntry
{
    public string $id;

    public UxAction $action = UxAction::Add;

    public ?string $slot = null;

    public ?string $after = null;

    public ?string $before = null;

    public ?string $condition = null;

    /**
     * Pins this entry to the very front of its slot, ahead of every entry without it -- unlike
     * `$after`/`$before`, which position an entry relative to another entry's id (and so depend
     * on that other entry actually being registered), this needs no anchor at all, for the
     * "this must lead the slot, full stop" case (e.g. the Admin panel link always leading a
     * user-menu dropdown when its own permission passes) that anchoring against a specific
     * other id can't guarantee -- nothing else in the slot is required to exist, let alone be
     * named, for this to hold. If more than one entry sets this, they keep their relative order
     * against each other (stable), all still ahead of everything else.
     */
    public bool $first = false;

    /**
     * Opts this entry out of the padded box `Card\Body` otherwise wraps every stacked entry
     * in -- for something meant to bleed edge-to-edge within its own section (a card image,
     * say) rather than sit inset like ordinary text content. Only `Body` reads this; entries
     * in `Top`/`Footer` render inline within one shared row instead of stacked boxes, so it's
     * meaningless there. Default `false` keeps every existing registration's current, padded
     * appearance unchanged.
     */
    public bool $flush = false;

    /**
     * Set by `SlotResolver::resolve()` right before rendering, when the slot being resolved
     * is bound to something (a `Moment`'s Card header, say) -- `null` for slots that aren't
     * (page-level ones like `kopling-core::community.navigation`). See `Context` itself for why this
     * carries the binding instead of a loose array merged into `$data`.
     */
    public ?Context $context = null;

    /**
     * @param  string  $component  A Blade component reference -- either an already-valid tag
     *                              ("k::portal.navigation.item") or the component's own FQCN
     *                              ("Item::class"), resolved to a tag by `ComponentTag` --
     *                              rendered via <x-dynamic-component>.
     * @param  array  $data  Passed whole as the single `data` prop -- static, author-declared
     *                        configuration for this registration (e.g. a reactions button's
     *                        own settings), as opposed to `$context`, which is the dynamic,
     *                        render-time binding. Every component a UxEntry can render (core-
     *                        provided or an extension's own) takes one `array $data`
     *                        constructor param, not a spread of named props, so any component
     *                        can be targeted without Manager/SlotResolver needing to know its
     *                        individual prop names.
     */
    public function __construct(
        public string $component,
        public array $data = [],
    ) {
        $this->id = $component;
        $this->component = ComponentTag::resolve($component);
    }

    /**
     * `$action`/`$context` are deliberately not included: by the time an entry survives into
     * `Manager::ux()`'s final, cacheable collection, every survivor's `$action` is already
     * `UxAction::Add` (`Replace`/`Remove` are operations applied against the registry, never
     * survivors themselves -- see `Manager::applyUxReplace()`/`applyUxRemove()`), and `$context`
     * is a render-time binding `SlotResolver::resolve()` sets per request, never part of the
     * static, cacheable shape.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slot' => $this->slot,
            'after' => $this->after,
            'before' => $this->before,
            'first' => $this->first,
            'flush' => $this->flush,
            'condition' => $this->condition,
            'component' => $this->component,
            'data' => $this->data,
        ];
    }

    public static function fromArray(array $data): self
    {
        $entry = new self($data['component'], $data['data']);
        $entry->id = $data['id'];
        $entry->slot = $data['slot'];
        $entry->after = $data['after'];
        $entry->before = $data['before'];
        $entry->first = $data['first'] ?? false;
        $entry->flush = $data['flush'] ?? false;
        $entry->condition = $data['condition'];

        return $entry;
    }
}
