<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A multi-line text setting field, rendered as a daisyUI `textarea`. Same shape and reasoning
 * as `Toggle`/`Input`: purely presentational, one `array $data` prop. Reads
 * `$data['name']`/`label`/`description`/`value`/`rows` (defaults to `3`)/`placeholder`.
 *
 * Unpacks `$data` into named view variables in `render()` -- see `Toggle`'s own docblock for
 * why a property literally named `data` can't be read straight from the Blade view.
 */
class TextArea extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.textarea', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'rows' => $this->data['rows'] ?? 3,
            'placeholder' => $this->data['placeholder'] ?? '',
            'value' => $this->data['value'] ?? $this->data['default'] ?? '',
        ]);
    }
}
