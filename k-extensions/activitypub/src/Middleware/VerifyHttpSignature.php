<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kopling\Activitypub\Federation\HttpSignature;
use Kopling\Activitypub\Federation\Manager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level only (never Portal-wide -- actor/webfinger GETs stay unauthenticated), and
 * rejects before any body parsing beyond the raw content string this itself needs for the
 * Digest check. Resolves + caches the sending actor via `Manager::resolveActor()` (Phase 5)
 * before verifying, so a first-contact actor still gets checked against its own real key, not
 * skipped for being unknown yet.
 */
class VerifyHttpSignature
{
    public function __construct(protected Manager $federation)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $signatureHeader = $request->header('Signature');
        $keyId = $signatureHeader ? HttpSignature::keyId($signatureHeader) : null;

        abort_unless($signatureHeader && $keyId, 401, 'Missing or malformed Signature header.');

        $actorUri = strtok($keyId, '#');

        // Checked before resolveActor() ever fetches anything -- a blocked domain shouldn't
        // cost this instance an outbound HTTP request just to find out it's blocked.
        abort_if($this->federation->isDomainBlocked((string) parse_url($actorUri, PHP_URL_HOST)), 403, 'Domain is blocked.');

        $sender = $this->federation->resolveActor($actorUri);
        $actor = $sender?->activitypubActor;

        abort_unless($actor?->public_key, 401, 'Unable to resolve signing actor.');

        $headers = collect($request->headers->all())
            ->map(fn (array $values) => $values[0])
            ->mapWithKeys(fn ($value, $name) => [strtolower($name) => $value])
            ->all();

        $verified = HttpSignature::verify(
            $actor->public_key,
            $request->method(),
            $request->getRequestUri(),
            $headers,
            $request->getContent(),
        );

        abort_unless($verified, 401, 'Invalid HTTP signature.');

        $request->attributes->set('activitypub_sender', $sender);

        return $next($request);
    }
}
