<?php

declare(strict_types=1);

namespace Kopling\Demo\Command;

use Illuminate\Console\Command;

class SeedFakeDataCommand extends Command
{
    protected $signature = 'kopling:demo:seed-fake-data';
    protected $description = 'Seed fake data for the demo extension';

    public function handle(): int
    {


        return self::SUCCESS;
    }
}
