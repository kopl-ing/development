<?php

declare(strict_types=1);

namespace Kopling\MailClient\Console;

use Illuminate\Console\Command;
use Kopling\MailClient\Jobs\SyncMailAccount;
use Kopling\MailClient\MailAccount;

/**
 * The degraded-host fallback: `QUEUE_CONNECTION=sync` by default means a sync that never gets
 * (re-)triggered by a real event has nothing driving it periodically -- this is what a host
 * operator with only cron, no persistent worker, runs instead (same posture as ActivityPub's own
 * `kopling:activitypub:deliver-pending`, not a new mechanism invented for mail). Re-dispatches
 * through the exact same SyncMailAccount path a real worker's own periodic trigger would use.
 */
class SyncPendingCommand extends Command
{
    protected $signature = 'kopling:mail-client:sync-pending';

    protected $description = 'Sync every connected mailbox due for a sync';

    /**
     * Matches the doc's "every 1-2 min per mailbox" polling cadence.
     */
    protected const SYNC_INTERVAL_MINUTES = 2;

    /**
     * Skips an account whose sync attempt started more recently than this -- one still genuinely
     * in flight on a real queue worker shouldn't also get picked up here and double-dispatched.
     * Mirrors ActivitypubDelivery's own retry-recency guard.
     */
    protected const STUCK_AFTER_MINUTES = 10;

    public function handle(): int
    {
        $due = MailAccount::query()
            ->where(function ($query) {
                $query->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subMinutes(self::SYNC_INTERVAL_MINUTES));
            })
            ->where(function ($query) {
                $query->whereNull('syncing_since')
                    ->orWhere('syncing_since', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES));
            })
            ->get();

        foreach ($due as $account) {
            SyncMailAccount::dispatch($account->id);
        }

        $this->components->info("Dispatched sync for {$due->count()} mailbox(es).");

        return self::SUCCESS;
    }
}
