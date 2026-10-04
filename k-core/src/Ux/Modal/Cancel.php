<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Modal;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A "Cancel" button for a form inside `<x-k::modal>`, placed next to the form's own submit
 * button. `formmethod="dialog"` closes the surrounding dialog without sending the form, and
 * `formnovalidate` keeps required fields from blocking that -- no JS involved.
 */
class Cancel extends Component
{
    public function render(): View
    {
        return view('kopling-core::ux.modal.cancel');
    }
}
