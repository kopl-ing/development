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
 * `min`/`max` (both optional) render as a hint below the list -- purely informational, this
 * component enforces nothing server-side; a caller wanting real enforcement declares its own
 * validation rule (e.g. via `ValidatesModels`) with the same numbers.
 *
 * The wrapper (legend, scrollable box, count hint, description) is the genuinely reusable part;
 * per-option *rendering* is enrichable via the default slot -- pass real markup there and it
 * replaces the plain-label loop entirely, with `$name`/currently-selected values already in the
 * caller's own scope to build it from (the caller is the one that constructed this component's
 * `data`, so it never needs anything threaded back out). No slot content falls back to the
 * plain checkbox-per-option default -- every existing caller (Person -> Group assignment)
 * renders identically either way.
 *
 * Deliberately not a native `<select multiple>` (poor UX, no daisyUI styling) -- a plain
 * checkbox list needs zero JS and fits a short, fully-known option set (Groups), staying
 * content-agnostic the same way `Toggle`/`Input`/`TextArea` don't know what setting they
 * render. For a large or dynamically-searched option set, see `Ux/Form/TagInput` instead
 * (`tags`' own picker moved there once it outgrew rendering every option up front).
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
