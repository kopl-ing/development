<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\CommandDeclarer;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\HasCommands;

class Extension extends AbstractExtension implements HasCommands
{
    public static function name(): string
    {
        return 'Command Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares a real artisan Command class, for testing HasCommands.';
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [PingCommand::class];
    }
}
