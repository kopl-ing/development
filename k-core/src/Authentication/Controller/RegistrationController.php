<?php

declare(strict_types=1);

namespace Kopling\Core\Authentication\Controller;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Kopling\Core\Http\Controllers\Concerns\RedirectsUsers;
use Kopling\Core\People\Person;

/**
 * Same method shape as laravel/ui's RegistersUsers trait (validator()/create()/register()/etc.)
 * -- that package isn't installed here (its traits were split out of core Laravel in 6.0), so
 * this is hand-written directly on the controller rather than composed from it, same reasoning
 * as `LoginController`'s own docblock. Deliberately the full classic scaffold for now, not
 * Kopling's own final shape: `validator()`/`create()` hardcode a name/email/password shape
 * against `Person` directly, same as laravel/ui ships out of the box. Trim down once it's
 * settled what a real registration flow here actually needs.
 */
class RegistrationController
{
    use RedirectsUsers;

    protected string $redirectTo = '/';

    public function showRegistrationForm(Request $request): View
    {
        return view('kopling-core::auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $this->validator($request->all())->validate();

        event(new Registered($person = $this->create($request->all())));

        Auth::login($person);

        return redirect()->intended($this->redirectTo());
    }

    protected function validator(array $data): ValidatorContract
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.Person::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
    }

    protected function create(array $data): Person
    {
        return Person::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }
}
