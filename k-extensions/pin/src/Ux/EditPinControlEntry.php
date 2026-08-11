<?php

declare(strict_types=1);

namespace Kopling\Pin\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\People\Group;
use Kopling\Core\Ux\Context;
use Kopling\Pin\Pin;

/**
 * "Edit pin" -- only renders when this Moment currently has a pin (nothing when it doesn't, so
 * this entry contributes no `<li>` at all for an unpinned Moment; see `Card\Control`'s own
 * empty-entry skip). Split out from `PinControlEntry`'s own pin/unpin toggle so each is a
 * single action in its own `<li>`, matching every other Control::SLOT entry, instead of both
 * sharing one wrapper the way this used to render.
 */
class EditPinControlEntry extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $moment = $this->context?->getSubject();

        return view('kopling-pin::ux.edit-pin-control-entry', [
            'moment' => $moment,
            'pin' => $moment?->pin,
            'reasons' => collect(Pin::REASONS)
                ->keys()
                ->mapWithKeys(fn (string $id) => [$id => __("kopling-pin::pin.reasons.{$id}")]),
            'groups' => Group::orderBy('name')->get(),
        ]);
    }
}
