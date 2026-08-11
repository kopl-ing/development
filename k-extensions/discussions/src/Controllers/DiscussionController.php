<?php

declare(strict_types=1);

namespace Kopling\Discussions\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;
use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Discussions\Reply;
use Kopling\Discussions\Requests\StoreReplyRequest;
use Kopling\Discussions\Requests\UpdateReplyRequest;

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

        $context = new Context(subject: Reply::forMoment($moment));
        $replies = $context->getSubjectPaginator();

        // A reading-position dock (reply-dock's own scrubber) needs the whole thread's real
        // total/page position to count correctly across pages -- shared rather than threaded
        // through `kopling-discussions::show.composer`'s own `$context` (that slot's `$context`
        // is documented as the Moment itself; several registered entries already read
        // `$context?->getSubject()` expecting exactly that, so repurposing it here would break
        // them). `$replies` is reused, not re-queried -- `Context::getSubjectPaginator()` is
        // memoized, so this is the exact same paginator `show.blade.php` already resolved.
        ViewFacade::share('discussionReplies', $replies);

        return view('kopling-discussions::show', [
            'moment' => $moment,
            'context' => $context,
            'replies' => $replies,
        ]);
    }

    /**
     * Post a reply, then return just the new reply so htmx can append it to the thread
     * (hx-swap="beforeend"). Guests abort 401 -> core's RedirectUnauthenticated. `body_html` is
     * rendered server-side from the validated `body` document here, at write time -- never
     * trusted directly from the client (see `DocumentRenderer`'s own docblock). Same
     * `HX-Request` branch `ComposerController::store()` establishes -- a plain POST (no htmx
     * request header) redirects back to the discussion page instead of rendering a bare
     * fragment as the entire response.
     */
    public function reply(StoreReplyRequest $request, Moment $moment, Manager $manager): View|RedirectResponse
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

        if (! $request->header('HX-Request')) {
            return redirect()->route('kopling-core::community/discussions.show', $moment);
        }

        return view('kopling-discussions::partials.reply', ['reply' => $reply]);
    }

    /**
     * The edit form fragment, swapped in over the reply's own card
     * (`hx-target="closest .card"`, see `ux/edit-control-entry.blade.php`) -- authorization is
     * a plain ownership check, same as `UpdateReplyRequest::authorize()`.
     */
    public function editReply(Reply $reply): View
    {
        abort_unless($reply->person_id === Auth::id(), 403);

        return view('kopling-discussions::partials.edit', ['reply' => $reply]);
    }

    /**
     * `body_html` is re-rendered server-side from the validated `body` document here, at write
     * time, exactly like `reply()` -- never trusted directly from the client.
     */
    public function updateReply(UpdateReplyRequest $request, Reply $reply, Manager $manager): View|RedirectResponse
    {
        $body = (string) $request->validated('body');

        $reply->update([
            'body' => $body,
            'body_html' => DocumentRenderer::render($body, $manager->editorNodes()),
        ]);

        $reply->load('person');

        if (! $request->header('HX-Request')) {
            return redirect()->route('kopling-core::community/discussions.show', $reply->moment_id);
        }

        return view('kopling-discussions::partials.reply', ['reply' => $reply]);
    }

    /**
     * The read-only card fragment, re-rendered to swap back over the edit form -- the "Cancel"
     * target, same reasoning as `ComposerController::show()`.
     */
    public function showReply(Reply $reply): View
    {
        $reply->load('person');

        return view('kopling-discussions::partials.reply', ['reply' => $reply]);
    }
}
