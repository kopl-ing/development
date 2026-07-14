<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * A generic daisyUI dropdown -- trigger button + popover menu, built on the CSS Anchor
 * Positioning/Popover API syntax daisyUI recommends (over its older CSS-focus fallback, which
 * needs no popover support but traps less predictably). Purely presentational, same as `Row`/
 * `Column`: takes no opinion on what's inside the menu, so both slot-driven consumers (`Card\
 * Control`) and anything else that just wants a dropdown can reuse it instead of hand-rolling
 * their own Alpine/daisyUI markup the way `k-extensions/reactions`' modal had to.
 *
 * `$id` is generated fresh per instance so multiple dropdowns rendered on one page (e.g. one per
 * card in a feed) never collide on their popover target / anchor name. Takes a `trigger` named
 * slot for the button's contents and the default slot for the menu's `<li>` items -- it doesn't
 * render `<li>` itself, since a caller may want plain content instead of a menu.
 */
class Dropdown extends Component
{
    public string $id;

    public function __construct(
        public string $label,
        public string $align = 'dropdown-end',
    ) {
        $this->id = 'dropdown-'.Str::random(8);
    }

    public function render(): View
    {
        return view('kopling-core::ux.dropdown');
    }
}
