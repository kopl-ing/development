<?php

declare(strict_types=1);

namespace Kopling\Discussions\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Content\Moment;
use Kopling\Discussions\Reply;

class DiscussionController
{
    /**
     * The discussion page for one moment -- the moment itself plus its reply thread and a
     * composer. Its own route + page (reusing the base portal shell + core's card), the same
     * pattern the tags extension uses, rather than anything bolted onto core's feed.
     */
    public function show(Moment $moment): View
    {
        return view('kopling-discussions::show', [
            'moment' => $moment,
            'replies' => Reply::forMoment($moment),
        ]);
    }

    /**
     * Post a reply, then return just the new reply so htmx can append it to the thread
     * (hx-swap="beforeend"). Guests abort 401 -> core's RedirectHtmxUnauthenticated.
     */
    public function reply(Request $request, Moment $moment): View
    {
        $person = Auth::user();

        $body = trim((string) $request->input('body', ''));
        abort_if($body === '', 422);

        /** @var Reply $reply */
        $reply = Reply::create([
            'moment_id' => $moment->id,
            'person_id' => $person->id,
            'body' => $body,
        ]);

        $reply->setRelation('person', $person);

        return view('kopling-discussions::partials.reply', ['reply' => $reply]);
    }
}
