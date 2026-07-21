<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A multi-value setting field, rendered as a scrollable list of daisyUI checkboxes -- not a
 * native `<select multiple>`, for a short, fully-known option set (see `TagInput` for large or
 * searched sets). Submits as `name="{name}[]"` -- the browser omits the key entirely when
 * nothing is checked, so a consuming controller must default to an empty array on read. `min`/
 * `max` are informational only, enforced nowhere server-side.
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
            'min' => $this->data['min'] ?? null,
            'max' => $this->data['max'] ?? null,
        ]);
    }
}
