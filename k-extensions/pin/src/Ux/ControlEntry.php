<?php

declare(strict_types=1);

namespace Kopling\Pin\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\People\Group;
use Kopling\Core\Ux\Context;
use Kopling\Pin\Pin;

/**
 * The single entry `Extension::ux()` registers into `Card\Control::SLOT` -- whether it renders
 * a "Pin" trigger or an "Edit pin" trigger plus an "Unpin" action depends on whether *this*
 * Moment currently has a pin, which is per-Moment state, not per-actor -- something `->when()`'s
 * permission gate can't express, so this component's own view decides instead. `Control`'s view
 * already wraps every entry in one `<li>`, so both actions render as siblings inside that same
 * `<li>`, not a second one.
 */
class ControlEntry extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        $moment = $this->context?->getSubject();

        return view('kopling-pin::ux.control-entry', [
            'moment' => $moment,
            'pin' => $moment?->pin,
            'reasons' => collect(Pin::REASONS)
                ->keys()
                ->mapWithKeys(fn (string $id) => [$id => __("kopling-pin::pin.reasons.{$id}")]),
            'groups' => Group::orderBy('name')->get(),
        ]);
    }
}
