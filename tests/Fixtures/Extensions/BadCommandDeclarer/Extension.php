<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\BadCommandDeclarer;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasCommands;

class Extension extends AbstractExtension implements HasCommands
{
    public static function name(): string
    {
        return 'Bad Command Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares a class-string that is not an artisan Command, for testing Manager::commands()\'s guard.';
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [\stdClass::class];
    }
}
