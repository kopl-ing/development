<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Barebones for now -- renders the page shell only. Same reasoning as `LoginController`:
 * the actual registration form/handling belongs to whichever extension implements a login
 * method, not core.
 */
class RegistrationController
{
    public function __invoke(): View
    {
        return view('core::auth.register');
    }
}
