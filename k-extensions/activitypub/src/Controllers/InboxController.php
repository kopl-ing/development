<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kopling\Activitypub\Jobs\ProcessInboundActivity;
use Kopling\Core\People\Person;

/**
 * Both the per-actor inbox (`/ap/people/{person}/inbox`) and the shared inbox
 * (`/ap/inbox`) resolve here -- nothing in v1 needs to know which of a Person's own inboxes an
 * activity arrived on, only who signed it (`VerifyHttpSignature` already resolved and verified
 * the sender before this runs). Dispatches rather than processes inline: the request is
 * verified, but its *contents* aren't trusted yet -- see `ProcessInboundActivity`.
 */
class InboxController
{
    public function __invoke(Request $request): Response
    {
        $activity = $request->json()->all();
        $sender = $request->attributes->get('activitypub_sender');

        abort_unless(is_array($activity) && $sender instanceof Person, 400);

        ProcessInboundActivity::dispatch($activity, $sender->id);

        return response()->noContent(202);
    }
}
