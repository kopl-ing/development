<?php

declare(strict_types=1);

namespace Kopling\Composer\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;

class ComposerController
{
    /**
     * Post a moment, then return just the new moment rendered through core's own card so htmx
     * can prepend it to the feed (hx-swap="afterbegin") — the same card component the feed and
     * the poller use, so an extension's card additions appear on it too. Title is optional
     * (charter: title-optional). Without htmx (no-JS) it redirects back to the feed instead.
     */
    public function store(Request $request, Manager $manager): View|RedirectResponse
    {
        $person = Auth::user();

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));

        abort_if($body === '', 422);

        /** @var Moment $moment */
        $moment = Moment::create([
            'person_id' => $person->id,
            'title' => $title !== '' ? $title : null,
            'body' => $body,
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
