<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A single-value setting field, rendered as a native daisyUI-styled `<select>` -- same shape as
 * `Toggle`/`MultiSelect`: one `array $data` constructor param, content-agnostic. Reads
 * `$data['name']`/`label`/`description`/`options` (`array<id, label>`)/`value` (falls back to
 * `default`). The single-value counterpart `Icon.php`'s own docblock already anticipated
 * ("a future `HasAdminSettings` `Select` field").
 */
class Select extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.select', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'options' => $this->data['options'] ?? [],
            'value' => isset($this->data['value']) || isset($this->data['default'])
                ? (string) ($this->data['value'] ?? $this->data['default'])
                : null,
        ]);
    }
}
