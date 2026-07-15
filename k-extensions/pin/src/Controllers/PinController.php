<?php

declare(strict_types=1);

namespace Kopling\Pin\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\Core\Content\Moment;
use Kopling\Pin\Pin;

class PinController
{
    use AuthorizesRequests;

    /**
     * Pins (or, on a moment that's already pinned, replaces the existing pin's) reason/
     * schedule/groups in one call -- `updateOrCreate` keyed on `moment_id` is what "one active
     * pin per moment, re-pinning replaces it" actually means at the data layer.
     */
    public function store(Request $request, Moment $moment): RedirectResponse
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

        return back();
    }

    public function destroy(Moment $moment): RedirectResponse
    {
        $this->authorize('kopling-pin::pin-moments');

        $moment->pin?->delete();

        return back();
    }
}
