<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A multi-value setting field, rendered as a scrollable list of daisyUI `checkbox`es -- same
 * shape/reasoning as `Toggle`: purely presentational, one `array $data` constructor param.
 * Reads `$data['name']`/`label`/`description`/`options` (`array<id, label>`)/`value` (array of
 * currently-selected ids, falling back to `default`). Submits as `name="{name}[]"` -- the
 * browser omits the key entirely when nothing is checked, so a consuming controller must
 * default to an empty array on read (e.g. `$request->input('groups', [])`).
 *
 * Deliberately not a native `<select multiple>` (poor UX, no daisyUI styling) or a searchable
 * combobox (no current caller needs search over a large option set) -- a plain checkbox list
 * needs zero JS and covers every known consumer (Person -> Group assignment; Pin's own optional
 * Groups targeting, later), staying content-agnostic the same way `Toggle`/`Input`/`TextArea`
 * don't know what setting they render.
 */
class MultiSelect extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.multi-select', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'options' => $this->data['options'] ?? [],
            'values' => collect($this->data['value'] ?? $this->data['default'] ?? [])
                ->map(fn ($value) => (string) $value)
                ->all(),
        ]);
    }
}
