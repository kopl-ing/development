<?php

declare(strict_types=1);

namespace Kopling\Composer\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Kopling\Composer\Requests\StoreMomentRequest;
use Kopling\Composer\Requests\UpdateMomentRequest;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\DocumentRenderer;

class ComposerController
{
    use AuthorizesRequests;

    /**
     * Post a moment, then return just the new moment rendered through core's own card so htmx
     * can prepend it to the feed (hx-swap="afterbegin") — the same card component the feed and
     * the poller use, so an extension's card additions appear on it too. Title is always
     * required; `body` isn't -- a feature-only moment (a poll, say) can leave it out entirely.
     * Without htmx (no-JS) it redirects back to the feed instead. `body_html` is rendered
     * server-side from the validated `body` document here, at write time -- never trusted
     * directly from the client (see `DocumentRenderer`'s own docblock).
     */
    public function store(StoreMomentRequest $request, Manager $manager): View|RedirectResponse
    {
        $person = Auth::user();

        // Model-scoped, e.g. tags' own per-tag posting restriction (see `ServiceProvider`'s
        // `Gate::before()`) -- `Moment::draft()` stands in for the not-yet-created moment so the
        // check has a real instance to resolve against, matching its own documented purpose.
        $this->authorize('create', Moment::draft());

        $body = $request->validated('body');

        /** @var Moment $moment */
        $moment = Moment::create([
            'person_id' => $person->id,
            'title' => $request->validated('title'),
            'body' => $body,
            'body_html' => $body !== null ? DocumentRenderer::render($body, $manager->editorNodes()) : null,
        ]);

        $moment->setRelation('person', $person);

        if (! $request->header('HX-Request')) {
            return redirect()->route('kopling-core::community/community');
        }

        return view('kopling-composer::partials.moment', [
            'moment' => $moment,
            'portal' => $manager->portals()->firstWhere('id', 'kopling-core::community'),
        ]);
    }

    /**
     * The edit form fragment, swapped in over the moment's own card
     * (`hx-target="closest .card"`, see `ux/edit-control-entry.blade.php`) -- authorization is
     * a plain ownership check, same as `UpdateMomentRequest::authorize()`, since there's no
     * Policy mechanism in this codebase to hang it on instead.
     */
    public function edit(Moment $moment): View
    {
        abort_unless($moment->person_id === Auth::id(), 403);

        return view('kopling-composer::partials.edit', ['moment' => $moment]);
    }

    /**
     * `body_html` is re-rendered server-side from the validated `body` document here, at write
     * time, exactly like `store()` -- never trusted directly from the client.
     */
    public function update(UpdateMomentRequest $request, Moment $moment, Manager $manager): View|RedirectResponse
    {
        $body = $request->validated('body');

        $moment->update([
            'title' => $request->validated('title'),
            'body' => $body,
            'body_html' => $body !== null ? DocumentRenderer::render($body, $manager->editorNodes()) : null,
        ]);

        $moment->load('person');

        if (! $request->header('HX-Request')) {
            return redirect()->route('kopling-core::community/community');
        }

        return view('kopling-composer::partials.moment', [
            'moment' => $moment,
            'portal' => $manager->portals()->firstWhere('id', 'kopling-core::community'),
        ]);
    }

    /**
     * The read-only card fragment, re-rendered to swap back over the edit form -- the "Cancel"
     * target (`ux/edit-control-entry.blade.php`'s edit form has no other way to discard an
     * in-progress edit and return to what's actually persisted).
     */
    public function show(Moment $moment, Manager $manager): View
    {
        $moment->load('person');

        return view('kopling-composer::partials.moment', [
            'moment' => $moment,
            'portal' => $manager->portals()->firstWhere('id', 'kopling-core::community'),
        ]);
    }
}
