<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

/**
 * A semantic icon name declared via `HasIcons::icons()`, with a mandatory Font Awesome
 * `$default` guaranteed to render regardless of which icon pack is active. `$default` is never
 * validated against Font Awesome's real catalog -- a typo is an author's own bug, surfaced at
 * render time (see `Ux\Icon`). A `ChangesIcons` pack may supply its own icon for this same id.
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
