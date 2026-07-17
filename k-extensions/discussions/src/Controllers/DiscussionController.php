<?php

declare(strict_types=1);

namespace Kopling\Discussions\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Discussions\Reply;
use Kopling\Discussions\Requests\StoreReplyRequest;

class DiscussionController
{
    use AuthorizesRequests;
    /**
     * The discussion page for one moment -- the moment itself plus its reply thread and a
     * composer. Its own route + page (reusing the base portal shell + core's card), the same
     * pattern the tags extension uses, rather than anything bolted onto core's feed.
     */
    public function show(Moment $moment): View
    {
        $this->authorize('kopling-discussions::view');

        return view('kopling-discussions::show', [
            'moment' => $moment,
            'replies' => Reply::forMoment($moment),
        ]);
    }

    /**
     * Post a reply, then return just the new reply so htmx can append it to the thread
     * (hx-swap="beforeend"). Guests abort 401 -> core's RedirectUnauthenticated. `body_html` is
     * rendered server-side from the validated `body` document here, at write time -- never
     * trusted directly from the client (see `DocumentRenderer`'s own docblock).
     */
    public function reply(StoreReplyRequest $request, Moment $moment, Manager $manager): View
    {
        $person = Auth::user();

        $body = (string) $request->validated('body');

        /** @var Reply $reply */
        $reply = Reply::create([
            'moment_id' => $moment->id,
            'person_id' => $person->id,
            'body' => $body,
            'body_html' => DocumentRenderer::render($body, $manager->editorNodes()),
        ]);

        $reply->setRelation('person', $person);

        return view('kopling-discussions::partials.reply', ['reply' => $reply]);
    }
}
