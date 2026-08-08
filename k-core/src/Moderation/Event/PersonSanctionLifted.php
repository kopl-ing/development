<?php

declare(strict_types=1);

namespace Kopling\Core\Moderation\Event;

use Kopling\Core\People\Person;
use Kopling\Core\People\Sanction;

class PersonSanctionLifted
{
    public function __construct(
        public Person $person,
        public Sanction $sanction,
    ) {
    }
}
