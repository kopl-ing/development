<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Form\IconSearch\IconRenderer;

/**
 * A searchable single-icon setting field, backed by Font Awesome's public GraphQL API for
 * search (`Http\Controllers\IconSearchController` -> `Ux\Form\IconSearch\FontAwesomeIconSearch`)
 * but rendered entirely from locally-bundled Blade Icons SVGs -- the API is only ever a name
 * index, never a source of markup (see `IconRenderer`, shared with the search side so both
 * agree on what counts as "resolvable"). Same `array $data` shape as `EmojiPicker`/`Input` --
 * reads `name`/`label`/`description`/`value`. `value` is a bare Font Awesome icon id (e.g.
 * "star"), not a `<x-k::icon>`-style declared semantic id: this is a free admin choice from
 * Font Awesome's whole catalog, not a fixed small set an extension declares via `HasIcons`.
 *
 * `searchUrl` always defaults to Core's own shared endpoint rather than requiring a caller to
 * supply one the way `TagInput` does: what an icon search returns never varies by caller,
 * unlike a tag search, so there's exactly one endpoint for every `IconPicker` anywhere to
 * share, not one each caller has to own.
 *
 * `$data['color']` is an optional hint tinting the current-value preview (e.g. a Tag's own
 * `color` field) -- purely cosmetic, read once at render time like every other field here;
 * it doesn't travel with the stored `value` itself.
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
