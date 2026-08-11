<?php

declare(strict_types=1);

namespace Kopling\Pin\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\People\Group;
use Kopling\Core\Ux\Context;
use Kopling\Pin\Pin;

/**
 * The pin/unpin toggle -- "Pin" (with the reason/scheduling/groups form) when this Moment has
 * no pin yet, "Unpin" when it does. A single action per render, its own `<li>` (see
 * `Card\Control`'s own view) -- `EditPinControlEntry` is the sibling entry for editing an
 * existing pin's own settings, split out for the same reason, rather than both stacked together
 * inside one shared wrapper the way this used to work.
 */
class PinControlEntry extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $moment = $this->context?->getSubject();

        return view('kopling-pin::ux.pin-control-entry', [
            'moment' => $moment,
            'pin' => $moment?->pin,
            'reasons' => collect(Pin::REASONS)
                ->keys()
                ->mapWithKeys(fn (string $id) => [$id => __("kopling-pin::pin.reasons.{$id}")]),
            'groups' => Group::orderBy('name')->get(),
        ]);
    }
}
