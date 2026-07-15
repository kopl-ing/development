<?php

declare(strict_types=1);

namespace Kopling\Pin\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;
use Kopling\Pin\Pin;

/**
 * Fills the community layout's already-existing `kopling-core::community.content-top` slot with
 * whatever's currently pinned and visible to the viewer -- reuses the same `<x-k::card.card>`
 * render path `community/moment.blade.php` does, so Top/Body/Footer/Control (including this
 * extension's own ControlEntry) keep working normally on a pinned card. `Portal\Slot` doesn't
 * bind a `Context` to page-level entries (nothing to bind to), so this reads the current actor
 * itself rather than through a passed-in Context.
 */
class PinnedSection extends Component
{
    public function render(): View
    {
        $pins = Pin::visibleFor(Auth::user());

        return view('kopling-pin::ux.pinned-section', [
            'pins' => $pins,
            'contexts' => $pins->map(fn (Pin $pin) => new Context(subject: $pin->moment)),
        ]);
    }
}
