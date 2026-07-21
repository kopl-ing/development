<?php

declare(strict_types=1);

namespace Kopling\Pin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\Manager;
use Kopling\Pin\Pin;

class PinController
{
    use AuthorizesRequests;

    /**
     * Pins (or, on a moment that's already pinned, replaces the existing pin's) reason/
     * schedule/groups in one call -- `updateOrCreate` keyed on `moment_id` is what "one active
     * pin per moment, re-pinning replaces it" actually means at the data layer.
     */
    public function store(Request $request, Moment $moment, Manager $manager): View|RedirectResponse
    {
        $this->authorize('kopling-pin::pin-moments');

        $validated = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Pin::REASONS))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'groups' => ['array'],
            'groups.*' => ['uuid', 'exists:groups,id'],
        ]);

        $pin = Pin::updateOrCreate(
            ['moment_id' => $moment->id],
            [
                'reason' => $validated['reason'],
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ],
        );

        $pin->groups()->sync($validated['groups'] ?? []);

        return $this->respond($request, $moment, $manager);
    }

    public function destroy(Request $request, Moment $moment, Manager $manager): View|RedirectResponse
    {
        $this->authorize('kopling-pin::pin-moments');

        $moment->pin?->delete();

        return $this->respond($request, $moment, $manager);
    }

    /**
     * Same dual-path shape `ComposerController::store()` established: an htmx caller gets the
     * moment re-rendered through the exact same shared partial the feed itself uses (so any
     * extension's own card additions stay correct), a plain POST gets a redirect back.
     *
     * Doesn't address a re-pin/unpin moving the moment into or out of the separate pinned
     * section above the regular feed -- that's the same "no general float/reorder mechanism"
     * gap already tracked in roadmap.md, not something a same-card swap can fix.
     */
    protected function respond(Request $request, Moment $moment, Manager $manager): View|RedirectResponse
    {
        if (! $request->header('HX-Request')) {
            return back();
        }

        return view('kopling-core::community.moment', [
            'moment' => $moment->fresh(),
            'portal' => $manager->portals()->firstWhere('id', 'kopling-core::community'),
        ]);
    }
}
