<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

/**
 * A semantic icon name -- "home", "reply", "sticky" -- an extension (or Core) declares once via
 * `HasIcons::icons()`, together with a mandatory Font Awesome equivalent (`$default`, e.g.
 * "fas-house") that's guaranteed to render regardless of which icon pack, if any, is active.
 * `$id` is set by the author as just the local part; `Manager::icons()` prefixes it with the
 * owning extension's own `id()` before it's ever referenced, same collision-safety rule as
 * `Permission::$id`.
 *
 * `$default` is never validated against Font Awesome's real icon list -- Manager has no way to
 * check that without hardcoding a specific Font Awesome version's catalog, the one thing this
 * class deliberately doesn't do (contrast with `Theme\Token`, a small closed enum Core does own
 * and validate against). A typo'd or non-existent Font Awesome name is an author's own bug,
 * surfaced at render time the same way any other icon-pack miss is (see `Ux\Icon`), not
 * something declaring an `Icon` can catch up front.
 *
 * A `ChangesIcons` icon-pack extension may additionally supply its own icon for this `$id` (see
 * `ChangesIcons::iconMap()`) -- `$default` is what renders when no active pack overrides it, not
 * the only thing this icon can ever look like.
 */
class Icon
{
    public function __construct(
        public string $id,
        public readonly string $label,
        public readonly string $default,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'default' => $this->default,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            label: $data['label'],
            default: $data['default'],
        );
    }
}
