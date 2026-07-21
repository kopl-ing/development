<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * A generic modal dialog -- trigger button + native `<dialog>` panel (real focus-trapping via
 * `showModal()`, unlike `Dropdown`'s Popover-API approach). `$id` slugs `$label` with a random
 * suffix by default, or pass one explicitly for a stable id a form can round-trip through
 * `old('_form')` to self-reopen after a failed validation redirect (see `ux/modal.blade.php`).
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
