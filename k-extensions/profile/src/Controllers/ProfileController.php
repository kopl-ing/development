<?php

declare(strict_types=1);

namespace Kopling\Profile\Controllers;

use Illuminate\Contracts\View\View;
use Kopling\Core\People\Person;

class ProfileController
{
    public function show(Person $person): View
    {
        return view('kopling-profile::show', [
            'person' => $person,
            'moments' => $person->moments()->paginate()
        ]);
    }
}
