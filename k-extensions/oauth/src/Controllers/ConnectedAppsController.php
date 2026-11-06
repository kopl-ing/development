<?php

declare(strict_types=1);

namespace Kopling\OAuth\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\OAuth\Person;
use Kopling\OAuth\Scopes;
use Kopling\OAuth\Tokens;
use Laravel\Passport\Passport;

class ConnectedAppsController
{
    public function createToken(Request $request): RedirectResponse
    {
        $person = Person::findOrFail($request->user()->id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::in(array_keys(Scopes::grantable($person)))],
        ]);

        Tokens::personalClient();
        $result = $person->createToken($data['name'], array_values($data['scopes']));

        return $this->back()->with('oauth_new_token', $result->accessToken);
    }

    public function revokeToken(Request $request, string $token): RedirectResponse
    {
        $token = Passport::token()->newQuery()->whereKey($token)->where('user_id', $request->user()->id)->firstOrFail();
        Tokens::revoke($token);

        return $this->back()->with('oauth_status', __('kopling-oauth::messages.revoked'));
    }

    public function revokeClient(Request $request, string $client): RedirectResponse
    {
        Tokens::revokeClientFor(Passport::client()->newQuery()->findOrFail($client), $request->user());

        return $this->back()->with('oauth_status', __('kopling-oauth::messages.revoked'));
    }

    private function back(): RedirectResponse
    {
        return redirect()->to(route('kopling-core::community/settings').'#connected-apps');
    }
}
