<?php

declare(strict_types=1);

namespace Kopling\Profile\Controllers;

use Illuminate\Contracts\View\View;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;

class ProfileController
{
    public function show(Person $person): View
    {
        // `Context::getSubjectPaginator()` is memoized -- reused, not re-queried, by
        // `<x-k::page.pagination :context="$context">` in the view.
        $context = new Context(subject: $person->moments()->getQuery());
        $moments = $context->getSubjectPaginator();

        return view('kopling-profile::show', [
            'person' => $person,
            'context' => $context,
            'moments' => $moments,
        ]);
    }
}
