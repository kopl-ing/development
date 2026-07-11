<?php

declare(strict_types=1);

namespace Kopling\AuthEmailPassword;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

class RegistrationForm extends Component
{
    public function __construct(
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-auth-email-password::registration-form');
    }
}
