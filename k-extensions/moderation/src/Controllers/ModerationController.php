<?php

declare(strict_types=1);

namespace Kopling\Moderation\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Kopling\Moderation\Flag;
use Kopling\Moderation\Support\ContentModerator;

class ModerationController
{
    use AuthorizesRequests;

    /**
     * Hide is triggered from a Card's own control menu -- the card itself is the htmx target
     * (`hx-target="closest .card"`, see `ux/hide-control-entry.blade.php`), so an htmx caller
     * gets an empty 200 back and the card simply disappears via `outerHTML` swap: once hidden,
     * there's no new state left to render in its place, unlike Pin's own pin/unpin toggle.
     */
    public function hide(Request $request, string $type, string $id, ContentModerator $contentModerator): RedirectResponse|Response
    {
        $this->authorize('kopling-moderation::moderate');

        $flaggable = Flag::resolveFlaggable($type, $id);
        $contentModerator->hide($flaggable, Auth::user(), $request->input('reason'));

        return $request->header('HX-Request') ? response('', 200) : back();
    }

    /**
     * Only ever reachable from the moderation queue itself (a hidden flaggable's card never
     * renders through the normal feed/thread to begin with, so there's no "Unhide" state to
     * reach from a Card's control menu) -- a plain POST + redirect, deliberately not `hx-boost`
     * (see the queue view's own comment on why that collides with `hide()`'s htmx branch above).
     */
    public function unhide(string $type, string $id, ContentModerator $contentModerator): RedirectResponse
    {
        $this->authorize('kopling-moderation::moderate');

        $contentModerator->unhide(Flag::resolveFlaggable($type, $id));

        return back();
    }

    /**
     * The cascade trigger (Phase 2) -- irreversible, so both the Card's own confirmation modal
     * (`ux/delete-control-entry.blade.php`) and the queue's row both require one before this is
     * ever reached. Same htmx-empty-vs-redirect split as `hide()`, for the same reason: reachable
     * from both a Card's own targeted `hx-post`+`hx-target="closest .card"` and the queue's plain
     * POST.
     */
    public function destroy(Request $request, string $type, string $id, ContentModerator $contentModerator): RedirectResponse|Response
    {
        $this->authorize('kopling-moderation::moderate');

        $flaggable = Flag::resolveFlaggable($type, $id);
        $contentModerator->delete($flaggable, Auth::user(), $request->input('reason'));

        return $request->header('HX-Request') ? response('', 200) : back();
    }
}
