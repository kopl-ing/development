<?php

declare(strict_types=1);

namespace Kopling\Composer\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Kopling\Composer\Requests\StoreMomentRequest;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Editor\DocumentRenderer;

class ComposerController
{
    /**
     * Post a moment, then return just the new moment rendered through core's own card so htmx
     * can prepend it to the feed (hx-swap="afterbegin") — the same card component the feed and
     * the poller use, so an extension's card additions appear on it too. Title is required --
     * `StoreMomentRequest` already guarantees a non-empty, trimmed string by the time this runs
     * (matching `moments.title`'s own `NOT NULL` column). Without htmx (no-JS) it redirects
     * back to the feed instead. `body_html` is rendered server-side from the validated `body`
     * document here, at write time -- never trusted directly from the client (see
     * `DocumentRenderer`'s own docblock).
     */
    public function store(StoreMomentRequest $request, Manager $manager): View|RedirectResponse
    {
        $person = Auth::user();
        $body = (string) $request->validated('body');

        /** @var Moment $moment */
        $moment = Moment::create([
            'person_id' => $person->id,
            'title' => $request->validated('title'),
            'body' => $body,
            'body_html' => DocumentRenderer::render($body, $manager->editorNodes()),
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
}
