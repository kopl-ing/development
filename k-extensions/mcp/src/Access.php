<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use Kopling\OAuth\Person;

/**
 * `use-mcp` is required twice on every call: as the person's permission and as a scope on their token.
 */
class Access
{
    public const PERMISSION = 'kopling-mcp::use-mcp';

    public const SCOPE = 'kopling-mcp::use-mcp';

    public static function allows(?Person $person, ?string $scope = null): bool
    {
        return $person !== null
            && $person->hasPermission(self::PERMISSION)
            && $person->tokenCan(self::SCOPE)
            && ($scope === null || $person->tokenCan($scope));
    }
}
