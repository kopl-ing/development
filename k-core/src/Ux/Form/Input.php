<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A single-line text/number/etc. setting field, rendered as a daisyUI `input`. Same shape and
 * reasoning as `Toggle`: purely presentational, one `array $data` prop. Reads
 * `$data['name']`/`label`/`description`/`value`/`type` (defaults to `"text"`)/`placeholder`.
 *
 * Unpacks `$data` into named view variables in `render()` -- see `Toggle`'s own docblock for
 * why a property literally named `data` can't be read straight from the Blade view.
 */
class Input extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.input', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'type' => $this->data['type'] ?? 'text',
            'placeholder' => $this->data['placeholder'] ?? '',
            'value' => $this->data['value'] ?? $this->data['default'] ?? '',
        ]);
    }
}
