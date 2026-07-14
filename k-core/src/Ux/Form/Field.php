<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Kopling\Core\Ux\ComponentTag;

/**
 * One admin-editable setting an extension declares via `HasAdminSettings::adminSettings()` --
 * what it is (label/description/default) and which `Ux\Form\*` component renders it. Carries no
 * opinion on persistence or placement (see `HasAdminSettings`'s own docblock).
 *
 * `$id` is set by the author as just the local part (e.g. "enabled"); `Manager::adminSettings()`
 * prefixes it with the owning extension's id before it's ever used as a storage key or form
 * field name, same convention as `Permission`/`Portal`/`UxEntry`, so two extensions can both
 * declare an "enabled" setting without colliding. `$component` goes through the same
 * `ComponentTag::resolve()` a `UxEntry` does, so it accepts either an already-valid tag
 * ("k::form.toggle") or the component's own FQCN (`Toggle::class`).
 */
class Field
{
    public string $id;

    public function __construct(
        string $id,
        public readonly string $label,
        public string $component,
        public readonly mixed $default = null,
        public readonly ?string $description = null,
        public readonly array $data = [],
    ) {
        $this->id = $id;
        $this->component = ComponentTag::resolve($component);
    }
}
