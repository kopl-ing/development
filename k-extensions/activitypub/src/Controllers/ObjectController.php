<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Controllers;

use Illuminate\Http\JsonResponse;
use Kopling\Activitypub\Federation\Manager;

/**
 * One route for every federatable non-Person model -- never hardcodes "Moment" or "Reply", so
 * a third federatable model later is an `Extend\Federation` registration, not a route change.
 * `Person` has its own dedicated `ActorController`/route instead (actors carry fields -- inbox,
 * keys -- no other federatable object does).
 */
class ObjectController
{
    public function __construct(protected Manager $federation)
    {
    }

    public function __invoke(string $type, string $id): JsonResponse
    {
        $registration = $this->federation->federationFor($type);

        abort_unless($registration, 404);

        $model = $registration->model::find($id);

        abort_unless($model, 404);

        return response()->json($this->federation->toActivityJson($registration, $model))
            ->header('Content-Type', 'application/activity+json');
    }
}
