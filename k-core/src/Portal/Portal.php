<?php

declare(strict_types=1);

namespace Kopling\Core\Portal;

/**
 * A named UI surface -- a route prefix + the Blade layout its routes render inside. Never a
 * gating mechanism itself: routes registered under a Portal still check their own granular
 * `Kopling\Core\Extend\Permission` exactly as they would anywhere else (see the charter,
 * D29 -- a Portal is never a disguised "is admin" flag).
 *
 * Carries no routes/js/css of its own -- not even for the extension that declares it. See
 * `Kopling\Core\Extension\Contract\ExtendsPortals`/`PortalExtension` for how anything actually
 * ends up registered under a Portal; this is deliberately the only mechanism, so a Portal with
 * nothing yet attached to it (silently registering zero routes, as `kopling/admin`'s did before
 * this split) is exactly as discoverable as one that does.
 */
class Portal
{
    /**
     * The path as originally declared by the extension -- never mutated, unlike `$path` itself
     * (which `Manager::portals()` overwrites with an admin-configured override when one exists,
     * the same way it already mutates `$id` for prefixing). Kept so admin UI can show "default
     * vs override" without the only copy of the default having been overwritten, and so the
     * override lookup always has a stable fallback to resolve against regardless of what `$path`
     * currently holds (see `Manager::portals()`).
     */
    public readonly string $defaultPath;

    public function __construct(
        public string $id,
        public readonly string $label,
        public string $path,
        public readonly string $layout,
        public readonly ?string $icon = null,
        public readonly ?string $description = null,
        public ?string $permission = null,
    ) {
        $this->defaultPath = $path;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'path' => $this->path,
            'defaultPath' => $this->defaultPath,
            'layout' => $this->layout,
            'icon' => $this->icon,
            'description' => $this->description,
            'permission' => $this->permission,
        ];
    }

    public static function fromArray(array $data): self
    {
        $portal = new self(
            id: $data['id'],
            label: $data['label'],
            path: $data['defaultPath'] ?? $data['path'],
            layout: $data['layout'],
            icon: $data['icon'],
            description: $data['description'],
            permission: $data['permission'],
        );

        $portal->path = $data['path'];

        return $portal;
    }
}
