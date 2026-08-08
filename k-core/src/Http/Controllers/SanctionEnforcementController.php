<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SanctionEnforcementController
{
    public function __invoke(Request $request): View
    {
        return view('kopling-core::auth.access-blocked', [
            'details' => $request->session()->get('access_blocked', []),
        ]);
    }
}
