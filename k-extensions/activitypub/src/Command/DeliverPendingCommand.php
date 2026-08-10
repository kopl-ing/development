<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Command;

use Illuminate\Console\Command;
use Kopling\Activitypub\ActivitypubDelivery;
use Kopling\Activitypub\Jobs\DeliverActivity;

/**
 * The degraded-host fallback: `QUEUE_CONNECTION=sync` by default means a delivery that failed
 * mid-request never gets a real queue worker's `tries`/`backoff` retrying it later -- this is
 * what a host operator with no worker, only cron, runs instead (same "polling/SSE-over-FPM
 * fallback, cron scheduler" posture the charter already establishes elsewhere, not a new
 * mechanism invented for federation). Re-dispatches through the exact same
 * `DeliverActivity`/`ActivitypubDelivery` path a real worker's retry would -- this command
 * doesn't reimplement delivery, just re-triggers it.
 */
class DeliverPendingCommand extends Command
{
    protected $signature = 'kopling:activitypub:deliver-pending';

    protected $description = 'Retry any outbound federation delivery that has not yet succeeded';

    /**
     * Attempts beyond this are considered permanently failed -- never retried again by this
     * command (a real queue worker's own `DeliverActivity::$tries` already gave up on these
     * long before cron would even see them at this count).
     */
    protected const MAX_ATTEMPTS = 10;

    /**
     * Skips anything updated more recently than this -- a delivery still in-flight on a real
     * queue worker right now shouldn't also get picked up here and double-attempted.
     */
    protected const RETRY_AFTER_MINUTES = 5;

    public function handle(): int
    {
        $pending = ActivitypubDelivery::query()
            ->whereNull('delivered_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('updated_at', '<', now()->subMinutes(self::RETRY_AFTER_MINUTES))
            ->get();

        foreach ($pending as $delivery) {
            DeliverActivity::dispatch($delivery->id);
        }

        $this->components->info("Retried {$pending->count()} pending federation deliveries.");

        return self::SUCCESS;
    }
}
