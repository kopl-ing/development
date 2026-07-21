<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A searchable, server-backed multi-select -- chosen values render as removable pills, typing
 * searches a caller-supplied URL instead of shipping every option up front. Built on
 * `@yaireo/tagify`. `searchUrl` is a `GET` endpoint taking `q`, returning JSON
 * `array<int, array{id: string, label: string}>`. `value` carries both id and label per entry --
 * a pill needs its label without a round-trip, and `old()` alone only remembers ids.
 */
class TagInput extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.tag-input', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'searchUrl' => $this->data['searchUrl'] ?? null,
            'placeholder' => $this->data['placeholder'] ?? null,
            'value' => $this->data['value'] ?? [],
            'min' => $this->data['min'] ?? null,
            'max' => $this->data['max'] ?? null,
        ]);
    }
}
