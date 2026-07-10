<?php

declare(strict_types=1);

namespace Kopling\Core\Authentication\Event;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Kopling\Core\People\Person;

class AttemptLogin
{
    public ?Person $person = null;
    public ValidationException $e;

    public function __construct(readonly public Request $request)
    {
        $this->e = ValidationException::withMessages([]);
    }

    public function failed(ValidationException $e): self
    {
        $this->e = $e;

        return $this;
    }

    public function succeeded(Person $person): self
    {
        $this->person = $person;

        return $this;
    }
}
