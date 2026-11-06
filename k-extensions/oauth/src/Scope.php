<?php

declare(strict_types=1);

namespace Kopling\OAuth;

/**
 * One OAuth scope another extension offers. `$permission` (a full, prefixed permission id) limits who may grant it.
 */
class Scope
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly ?string $permission = null,
    ) {
    }
}
