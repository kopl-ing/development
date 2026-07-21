<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

/**
 * A named, granular capability -- never a hardcoded "is admin" check. `$id` is the local part;
 * `Manager` prefixes it with the owning extension's id before registering it with the Gate.
 * `$default` grants to everyone including guests; `$allowsGuests` is exclusive, not additive --
 * checks *only* whether the visitor is a guest, for "sign in" style links.
 *
 * Not to be confused with `Kopling\Core\Authorization\Permission` -- the real Eloquent model
 * over a Group's granted permissions. This one is just what `HasPermissions::permissions()`
 * declares.
 */
class Permission
{
    public function __construct(
        public string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly ?bool $default = null,
        public readonly bool $allowsGuests = false,
    ) {
    }

    /**
     * @return array{id: string, label: string, description: string, default: ?bool, allowsGuests: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'default' => $this->default,
            'allowsGuests' => $this->allowsGuests,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            label: $data['label'],
            description: $data['description'],
            default: $data['default'],
            allowsGuests: $data['allowsGuests'],
        );
    }
}
