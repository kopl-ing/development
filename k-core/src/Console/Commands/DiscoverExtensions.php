<?php

declare(strict_types=1);

namespace Kopling\Core\Console\Commands;

use Illuminate\Console\Command;
use Kopling\Core\Extension\Manifest;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Kopling's counterpart to Laravel's own `package:discover` (Illuminate\Foundation\Console\
 * PackageDiscoverCommand) -- same pattern, rebuilding a different cache
 * (bootstrap/cache/kopling-extensions.php instead of packages.php). Wired into
 * composer.json's post-autoload-dump so it never needs remembering by hand.
 */
#[AsCommand(name: 'kopling:extensions:discover')]
class DiscoverExtensions extends Command
{
    protected $signature = 'kopling:extensions:discover';

    protected $description = 'Rebuild the cached Kopling extension manifest';

    public function handle(Manifest $manifest): void
    {
        $this->components->info('Discovering Kopling extensions');

        $manifest->build();
    }
}
