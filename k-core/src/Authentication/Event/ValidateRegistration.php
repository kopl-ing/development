<?php

declare(strict_types=1);

namespace Kopling\Core\Authentication\Event;

use Illuminate\Http\Request;

readonly class ValidateRegistration
{
    public function __construct(public Request $request)
    {}
}
