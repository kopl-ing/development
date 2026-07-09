<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;

class HomeController
{
    public function __invoke(): View
    {
        return view('core::index');
    }
}
