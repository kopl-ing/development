<?php

declare(strict_types=1);

namespace Kopling\Core\Authentication\Event;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Kopling\Core\People\Person;

/**
 * Unlike `AttemptLogin::succeeded()`, this `$person` isn't necessarily persisted --
 * `succeeded()` means "this is the person the attempt produced," not "this is saved."
 * `RegistrationController` only calls `save()` once, after every listener has had a chance to
 * run, so a listener registered after the one that actually created the `Person` (e.g. a
 * defaults/preferences extension) can still mutate the same instance in place before it's
 * written.
 */
class AttemptRegistration
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
