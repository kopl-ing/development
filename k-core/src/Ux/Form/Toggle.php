<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A boolean setting field, rendered as a daisyUI `toggle`. Purely presentational, same as
 * `Card\Row`/`Card\Column`: takes one `array $data` constructor param like every other
 * component a `UxEntry`-style registration can render, rather than a spread of named props, so
 * it can be targeted without the caller needing to know its individual prop names. Reads
 * `$data['name']`/`label`/`description`/`value` -- `name`/`value` are runtime concerns (the
 * field's already-prefixed id, and its current persisted-or-default value), filled in by
 * whoever resolves `HasAdminSettings::adminSettings()` at render time, not by the declaring
 * extension.
 *
 * Unpacks `$data` into named view variables in `render()` rather than reading `$data[...]`
 * straight from the Blade view -- a property literally named `data` never reaches the view via
 * Blade's usual public-property auto-exposure (`<x-dynamic-component>` reserves that name for
 * its own compiled internals), the same reason `Card\Avatar`/`Card\Author` already do this.
 */
class Toggle extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.toggle', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'checked' => (bool) ($this->data['value'] ?? $this->data['default'] ?? false),
        ]);
    }
}
