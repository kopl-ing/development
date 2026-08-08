<?php

declare(strict_types=1);

namespace Kopling\Moderation\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Kopling\Core\Moderation\ModerationReason;
use Kopling\Core\People\Person;
use Kopling\Core\People\Sanction;

/**
 * A thin wrapper around `Sanction::issue()`/`lift()` -- the mechanism and enforcement live in
 * `k-core` (`Sanction`, `EnforceSanctions`), this extension only owns the workflow/UI that gets
 * a moderator to those calls, the same split `Portal`/`Permission`/`Group` already have with
 * `admin`'s own CRUD around them.
 */
class SanctionController
{
    use AuthorizesRequests;

    /**
     * Checkbox fields (`communication_blocked`/`hide_content`/`access_blocked`) are absent from
     * the request entirely when unchecked -- standard HTML form behaviour, not a validation
     * gap -- so each reads via `?? false` rather than being `required`.
     */
    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('kopling-moderation::moderate');

        $validated = $request->validate([
            'communication_blocked' => ['boolean'],
            'hide_content' => ['boolean'],
            'access_blocked' => ['boolean'],
            'access_blocked_until' => ['nullable', 'date'],
            'reason' => ['required', Rule::enum(ModerationReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        Sanction::issue($person, [
            'communication_blocked' => (bool) ($validated['communication_blocked'] ?? false),
            'visibility' => ($validated['hide_content'] ?? false) ? 'hidden' : null,
            'access_blocked' => (bool) ($validated['access_blocked'] ?? false),
            'access_blocked_until' => $validated['access_blocked_until'] ?? null,
            'reason' => $validated['reason'],
            'note' => $validated['note'] ?? null,
        ], Auth::user());

        return back();
    }

    public function lift(Person $person): RedirectResponse
    {
        $this->authorize('kopling-moderation::moderate');

        Sanction::lift($person, Auth::user());

        return back();
    }
}
