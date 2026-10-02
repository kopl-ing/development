<?php

declare(strict_types=1);

namespace Kopling\Profile\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Kopling\Core\People\Person;

class ProfileController
{
    public function show(Person $person): View
    {
        abort_unless(Gate::allows('view', $person), 404);

        return view('kopling-profile::show', [
            'person' => $person,
        ]);
    }
}
