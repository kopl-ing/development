<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Illuminate\Support\Collection;
use Kopling\Core\People\Person as CorePerson;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Reading and revoking a person's tokens, shared by their settings section and the admin page.
 */
class Tokens
{
    public const PERSONAL_CLIENT = 'Personal tokens';

    /**
     * @return Collection<int, Token>
     */
    public static function active(?CorePerson $person = null): Collection
    {
        return Passport::token()->newQuery()
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($person, fn ($query) => $query->where('user_id', $person->id))
            ->with(['client', 'person'])
            ->latest()
            ->get();
    }

    public static function isPersonal(Token $token): bool
    {
        return $token->client?->hasGrantType('personal_access') ?? false;
    }

    public static function revoke(Token $token): void
    {
        $token->revoke();
        $token->refreshToken?->revoke();
    }

    public static function revokeClientFor(Client $client, ?CorePerson $person = null): void
    {
        Passport::token()->newQuery()
            ->where('client_id', $client->id)
            ->when($person, fn ($query) => $query->where('user_id', $person->id))
            ->where('revoked', false)
            ->get()
            ->each(fn (Token $token) => self::revoke($token));

        Passport::authCode()->newQuery()
            ->where('client_id', $client->id)
            ->when($person, fn ($query) => $query->where('user_id', $person->id))
            ->update(['revoked' => true]);
    }

    public static function personalClient(): Client
    {
        try {
            return app(ClientRepository::class)->personalAccessClient(ServiceProvider::PROVIDER);
        } catch (\RuntimeException) {
            return app(ClientRepository::class)->createPersonalAccessGrantClient(self::PERSONAL_CLIENT, ServiceProvider::PROVIDER);
        }
    }
}
