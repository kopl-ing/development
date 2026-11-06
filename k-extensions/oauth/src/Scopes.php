<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Kopling\Core\People\Person as CorePerson;
use Laravel\Passport\Passport;

/**
 * Every scope extensions register; kept in step with Passport's own list on every add.
 */
class Scopes
{
    /** @var array<string, Scope> */
    private static array $scopes = [];

    public static function add(Scope $scope): void
    {
        self::$scopes[$scope->id] = $scope;
        Passport::tokensCan(array_map(fn (Scope $scope) => $scope->description, self::$scopes));
    }

    /**
     * @return array<string, Scope>
     */
    public static function all(): array
    {
        return self::$scopes;
    }

    public static function find(string $id): ?Scope
    {
        return self::$scopes[$id] ?? null;
    }

    public static function grantableBy(?CorePerson $person, string $id): bool
    {
        $scope = self::find($id);

        return $scope !== null && $person !== null
            && ($scope->permission === null || $person->hasPermission($scope->permission));
    }

    /**
     * @return array<string, Scope>
     */
    public static function grantable(CorePerson $person): array
    {
        return array_filter(self::$scopes, fn (Scope $scope) => self::grantableBy($person, $scope->id));
    }

    public static function flush(): void
    {
        self::$scopes = [];
        Passport::tokensCan([]);
    }
}
