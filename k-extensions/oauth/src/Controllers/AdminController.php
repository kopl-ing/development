<?php

declare(strict_types=1);

namespace Kopling\OAuth\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\OAuth\Keys;
use Kopling\OAuth\Tokens;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class AdminController
{
    public function index(): View
    {
        $tokens = Tokens::active();

        return view('kopling-oauth::admin.index', [
            'keys' => Keys::exist(),
            'clients' => Passport::client()->newQuery()->orderByDesc('created_at')->get()
                ->reject(fn (Client $client) => $client->hasGrantType('personal_access'))
                ->map(fn (Client $client) => [
                    'client' => $client,
                    'people' => $tokens->where('client_id', $client->id)->pluck('user_id')->unique()->count(),
                ]),
            'tokens' => $tokens,
        ]);
    }

    public function generateKeys(Request $request): RedirectResponse
    {
        abort_if(Keys::exist() && ! $request->boolean('replace'), 422);

        Keys::generate();

        return redirect()->route('kopling-admin::admin/oauth')->with('status', __('kopling-oauth::messages.admin.keys_created'));
    }

    public function revokeClient(string $client): RedirectResponse
    {
        $client = Passport::client()->newQuery()->findOrFail($client);
        $client->forceFill(['revoked' => true])->save();
        Tokens::revokeClientFor($client);

        return redirect()->route('kopling-admin::admin/oauth')->with('status', __('kopling-oauth::messages.revoked'));
    }

    public function revokeToken(string $token): RedirectResponse
    {
        Tokens::revoke(Passport::token()->newQuery()->findOrFail($token));

        return redirect()->route('kopling-admin::admin/oauth')->with('status', __('kopling-oauth::messages.revoked'));
    }
}
