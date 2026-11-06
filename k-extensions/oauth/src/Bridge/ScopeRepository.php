<?php

declare(strict_types=1);

namespace Kopling\OAuth\Bridge;

use Kopling\OAuth\Person;
use Kopling\OAuth\Scopes;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Drops scopes the person may not grant (missing permission) and the `*` wildcard, whatever the client asked for.
 */
class ScopeRepository extends PassportScopeRepository
{
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        $scopes = parent::finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier, $authCodeId);

        if ($userIdentifier === null) {
            return $scopes;
        }

        $person = Person::find($userIdentifier);

        return array_values(array_filter(
            $scopes,
            fn (ScopeEntityInterface $scope) => $scope->getIdentifier() !== '*' && Scopes::grantableBy($person, $scope->getIdentifier()),
        ));
    }
}
