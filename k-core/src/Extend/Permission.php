<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

/**
 * A named, granular capability -- never a hardcoded "is admin" check. `$id` is set by the
 * author (core or an extension) as just the local part, e.g. "manage-people"; Manager
 * prefixes it with the extension's own id (or "core") before it's ever registered with the
 * Gate -- "kopling-example::manage-things", same `::` separator as views and translations --
 * so an author never has to think about collisions with another extension's names.
 *
 * `$default`, when true, grants this permission to literally everyone -- guest included, no
 * Group grant needed. `$allowsGuests` is a separate, narrower flag, and exclusive rather than
 * additive: when set, this permission checks *only* whether the current visitor is a guest --
 * a real Person can never hold it via a Group grant, even if one somehow got created (e.g. a
 * blanket "grant every permission" seed script) -- see `ServiceProvider::boot()`'s Gate closure.
 * Meant for UI that should show only when signed out (a "sign in" link, say); `$default`
 * already covers "granted to everyone" on its own, so the two never need to combine.
 *
 * Lives in `Extend`, not `Authorization`, alongside `Extend\Model`/`Extend\Ux` -- it's what an
 * extension's `HasPermissions::permissions()` declares, the same kind of thing those are, not a
 * persisted/runtime concept. `Kopling\Core\Authorization\Permission` is a different class
 * entirely: the real Eloquent model over a Group's actual granted permissions.
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
