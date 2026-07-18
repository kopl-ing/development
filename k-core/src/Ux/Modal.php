<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * A generic modal dialog -- trigger button + `<dialog>` panel, built on the native `<dialog>`
 * element rather than `Dropdown`'s Popover-API approach: a form-bearing modal (a settings form,
 * a confirmation) needs real focus-trapping, which `showModal()` gives natively (inert
 * background, focus trap, Escape closes), where the Popover API deliberately does not trap
 * focus. Purely presentational, same as `Dropdown`/`Row`/`Column` -- takes no opinion on what's
 * inside, so any extension needing a modal reuses this instead of hand-rolling its own Alpine
 * one the way `k-extensions/reactions`' modal had to.
 *
 * `$id` slugs `$label` (e.g. "modal-manage-groups-a1b2") so the markup stays readable in
 * devtools, with a short random suffix so multiple modals sharing the same label on one page
 * (e.g. one "Manage groups" modal per person row) never collide. Takes a `trigger` named slot
 * for the button's contents and the default slot for the panel's body.
 *
 * `$id` may be passed explicitly instead, when a caller needs a stable, predictable id to
 * target from its own JS (e.g. re-opening a specific create/edit dialog after a validation
 * redirect-back leaves it closed with errors sitting in the `$errors` bag -- see the tags
 * admin CRUD screen for the first caller doing this).
 */
class Modal extends Component
{
    public string $id;

    public function __construct(
        public string $label,
        ?string $id = null,
    ) {
        $this->id = $id ?? 'modal-'.Str::slug($label).'-'.Str::random(4);
    }

    public function render(): View
    {
        return view('kopling-core::ux.modal');
    }
}
