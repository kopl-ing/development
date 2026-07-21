<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Form\IconSearch\IconRenderer;

/**
 * A searchable single-icon setting field -- search hits Font Awesome's public GraphQL API
 * (`IconSearchController`), but every icon renders from locally-bundled Blade Icons SVGs (see
 * `IconRenderer`). `value` is a bare Font Awesome icon id, a free admin choice, not a declared
 * `HasIcons` semantic id. `searchUrl` defaults to Core's own shared endpoint, unlike `TagInput`,
 * since what an icon search returns never varies by caller.
 */
class IconPicker extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        $value = $this->data['value'] ?? $this->data['default'] ?? null;
        $color = $this->data['color'] ?? null;

        return view('kopling-core::ux.form.icon-picker', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'value' => $value,
            'icon' => $value ? IconRenderer::svg($value, color: $color) : null,
            'searchUrl' => $this->data['searchUrl'] ?? route('kopling-core::community/icon-search'),
            'placeholder' => $this->data['placeholder'] ?? null,
        ]);
    }
}
