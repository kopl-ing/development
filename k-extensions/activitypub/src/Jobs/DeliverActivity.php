<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Kopling\Activitypub\ActivitypubDelivery;
use Kopling\Activitypub\Federation\HttpSignature;
use Kopling\Activitypub\Federation\Manager;

/**
 * Signs and POSTs one already-persisted `ActivitypubDelivery` row -- see
 * `Federation\Manager::queueDelivery()` (the only way one of these ever gets dispatched) and the
 * migration's own docblock for why the attempt is a real row, not just this job's payload.
 * Idempotent: a delivery already marked `delivered_at` is skipped, so a retry racing a
 * still-in-flight original attempt (queue-driven `tries`/`backoff` alongside
 * `federation:deliver-pending`'s own cron-driven retry) never double-delivers.
 */
class DeliverActivity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(public string $deliveryId)
    {
    }

    public function handle(Manager $federation): void
    {
        $delivery = ActivitypubDelivery::find($this->deliveryId);

        if ($delivery === null || $delivery->isDelivered()) {
            return;
        }

        $person = $delivery->person;
        $actor = $person?->activitypubActor;

        if (! $actor?->isFederating() || $actor->private_key === null) {
            return;
        }

        if ($federation->isDomainBlocked((string) parse_url($delivery->inbox_url, PHP_URL_HOST))) {
            return;
        }

        $delivery->increment('attempts');

        try {
            $body = json_encode($delivery->activity, JSON_UNESCAPED_SLASHES);
            $keyId = $federation->canonicalActorUri($person).'#main-key';
            $headers = HttpSignature::sign($actor->private_key, $keyId, 'POST', $delivery->inbox_url, $body);

            Http::withHeaders($headers)
                ->withBody($body, 'application/activity+json')
                ->post($delivery->inbox_url)
                ->throw();

            $delivery->update(['delivered_at' => now(), 'last_error' => null]);
        } catch (\Throwable $e) {
            $delivery->update(['last_error' => $e->getMessage()]);

            throw $e;
        }
    }
}
