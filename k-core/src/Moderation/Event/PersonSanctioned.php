<?php

declare(strict_types=1);

namespace Kopling\Core\Moderation\Event;

use Kopling\Core\People\Person;
use Kopling\Core\People\Sanction;

/**
 * Fired by `Sanction::issue()` itself -- never something a caller has to remember to dispatch
 * separately, the same structural-not-conventional guarantee `ContentModerator`'s own writes
 * give `Kopling\Moderation\Event\ContentDeleted`. Core-owned, unlike that one, since `Sanction`
 * itself is a core primitive, not a `moderation`-extension-invented concept.
 */
class PersonSanctioned
{
    public function __construct(
        public Person $person,
        public Sanction $sanction,
    ) {
    }
}
