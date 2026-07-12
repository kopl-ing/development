<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\CommandDeclarer;

use Illuminate\Console\Command;

class PingCommand extends Command
{
    protected $signature = 'tests-fixtures:ping';

    protected $description = 'A fixture artisan command, for testing HasCommands.';

    public function handle(): int
    {
        $this->info('pong');

        return self::SUCCESS;
    }
}
