<?php

declare(strict_types=1);

namespace Kopling\Docs\Console;

use Illuminate\Console\Command;
use Kopling\Docs\PageRegistry;

class SyncDocs extends Command
{
    protected $signature = 'kopling:docs:sync';

    protected $description = 'Scan the docs content drive and rebuild the docs_pages index from it';

    public function handle(PageRegistry $registry): int
    {
        try {
            $written = $registry->sync();
        } catch (\RuntimeException $e) {
            // Resolver::resolve() throws exactly this when kopling-docs::content isn't mapped
            // to an enabled drive yet -- never a silently-empty index, per Resolver's own
            // "never silently fall back" rule.
            $this->components->error($e->getMessage());
            $this->components->info('Map the "Docs content" storage request to a drive at /admin/storage, then run this again.');

            return self::FAILURE;
        }

        $this->components->info("Synced {$written} doc page(s).");

        return self::SUCCESS;
    }
}
