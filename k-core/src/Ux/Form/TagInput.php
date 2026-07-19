<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A searchable, server-backed multi-select -- chosen values render as removable pills, typing
 * searches a caller-supplied URL instead of shipping every possible option up front. Built on
 * `@yaireo/tagify` (MIT, `Ux/js/tag-input-tagify.js`) rather than hand-rolled, specifically
 * because it's a real, mature widget (keyboard nav, ARIA, edit-in-place all included) and its
 * own "mixed tags" mode leaves room for a future inline-mention feature (`@person`, `#tag`
 * typed directly into a moment's body) to reuse the same dependency later -- a plain enhanced
 * `<select>` library couldn't offer that. The `tags` picker on the compose form is the first
 * real caller; Core stays entirely ignorant of what's actually being searched, same separation
 * `EmojiPicker` already established -- this component owns the interaction, the caller owns the
 * domain (what a "tag" is, what its search results look like).
 *
 * Reads `$data['name']`/`label`/`description`/`min`/`max` (same shape/meaning as `MultiSelect`
 * -- a rendering hint only, not enforcement), `searchUrl` (required -- a `GET` endpoint taking
 * a `q` query param and returning JSON `array<int, array{id: string, label: string}>`, called
 * again with an empty `q` on focus so an empty query can still show something useful), `value`
 * (already-selected options in that same `{id, label}` shape -- both id *and* label, since a
 * pill needs to render its label without a round-trip, and `old('name', [])` alone only ever
 * remembers submitted ids, not labels; a caller repopulating after a validation error resolves
 * the labels itself, the same way `tags`' own admin form already resolves `old()` ids back to
 * real `Tag` rows for its other fields).
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
