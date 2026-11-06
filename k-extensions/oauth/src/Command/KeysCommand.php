<?php

declare(strict_types=1);

namespace Kopling\OAuth\Command;

use Illuminate\Console\Command;
use Kopling\OAuth\Keys;

class KeysCommand extends Command
{
    protected $signature = 'kopling:oauth:keys {--force : Replace existing keys, which invalidates every issued token}';

    protected $description = 'Create the key pair OAuth uses to sign tokens';

    public function handle(): int
    {
        if (Keys::exist() && ! $this->option('force')) {
            $this->components->info('Keys already exist. Use --force to replace them.');

            return self::SUCCESS;
        }

        Keys::generate();
        $this->components->info('Keys created.');

        return self::SUCCESS;
    }
}
