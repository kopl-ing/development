<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

/**
 * One piece of UI an extension (or core) places into a named slot -- e.g. a link in
 * `kopling-core::community.navigation`. Not readonly: `Ux::add()` returns an entry that
 * `in()`/`after()`/`before()`/`as()`/`when()` mutate as the fluent chain continues, and
 * `Manager::ux()` mutates an already-registered entry in place when `Ux::replace()` targets it.
 *
 * `$condition` is `null` (always visible), a local permission id (prefixed by Manager), or
 * another extension's already-qualified permission id -- told apart by whether it contains "::".
 * Never a closure, so every entry stays plain, cacheable data.
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
     * Pins this entry to the very front of its slot, ahead of every entry without it -- needs
     * no anchor, unlike `$after`/`$before`. Multiple `first` entries keep their relative order.
     */
    public bool $first = false;

    /**
     * Opts out of the padded box `Card\Body` otherwise wraps every stacked entry in -- for
     * something meant to bleed edge-to-edge (a card image). Only `Body` reads this.
     */
    public bool $flush = false;

    /**
     * Set by `SlotResolver::resolve()` when the slot is bound to something (a Moment's Card,
     * say) -- `null` for page-level slots.
     */
    public ?Context $context = null;

    /**
     * @param  string  $component  A Blade component reference -- an already-valid tag or the
     *                              component's own FQCN, resolved to a tag by `ComponentTag`.
     * @param  array  $data  Static, author-declared config for this registration, as opposed to
     *                        `$context`, the dynamic render-time binding.
     */
    public function __construct(
        public string $component,
        public array $data = [],
    ) {
        $this->id = $component;
        $this->component = ComponentTag::resolve($component);
    }

    /**
     * `$action`/`$context` are deliberately not included: every survivor's `$action` is already
     * `Add` by the time it reaches `Manager::ux()`'s cacheable collection, and `$context` is a
     * per-request render-time binding, not part of the static shape.
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
