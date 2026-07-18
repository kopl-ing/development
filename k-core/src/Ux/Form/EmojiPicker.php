<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A single-emoji setting field, backed by the emoji-mart picker (`Ux/js/emoji-picker.js` +
 * `emoji-picker-mart.js`, see their own docblocks) -- Core's one reusable emoji-picker
 * primitive, not something any individual extension hand-rolls again (`reactions`' own
 * `PALETTE`-grid picker predates this and stays as-is; this is for free-choice single-emoji
 * fields like a tag's `upvote_emoji`/`downvote_emoji`). Same `array $data` shape as
 * `Input`/`Toggle` -- reads `name`/`label`/`description`/`value`.
 *
 * Purely a form field: renders a hidden `<input>` (what a plain `<form>` POST/htmx already
 * picks up) plus a trigger button showing the current emoji, no JS-framework state of its own.
 */
class EmojiPicker extends Component
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.form.emoji-picker', [
            'name' => $this->data['name'] ?? null,
            'label' => $this->data['label'] ?? null,
            'description' => $this->data['description'] ?? null,
            'value' => $this->data['value'] ?? $this->data['default'] ?? null,
        ]);
    }
}
