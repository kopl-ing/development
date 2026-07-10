<?php

declare(strict_types=1);

namespace Kopling\Core\Authentication\Event;

use Illuminate\Http\Request;

readonly class ValidateLogin
{
    public function __construct(public Request $request)
    {}
}
