<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kopling\Activitypub\ActivitypubActor;
use Kopling\Activitypub\Federation\Manager;

class WebfingerController
{
    public function __construct(protected Manager $federation)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $resource = (string) $request->query('resource', '');

        if (! str_starts_with($resource, 'acct:') || ! str_contains($resource, '@')) {
            abort(404);
        }

        [$handle, $domain] = explode('@', substr($resource, strlen('acct:')), 2);

        if ($domain !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            abort(404);
        }

        $actor = ActivitypubActor::where('handle', $handle)->first();

        abort_unless($actor?->isFederating(), 404);

        return response()->json([
            'subject' => "acct:{$handle}@{$domain}",
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $this->federation->canonicalActorUri($actor->person),
                ],
            ],
        ])->header('Content-Type', 'application/jrd+json');
    }
}
