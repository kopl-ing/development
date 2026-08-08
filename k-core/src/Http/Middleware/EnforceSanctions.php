<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kopling\Core\People\Sanction;

/**
 * The per-request "is this person still allowed to be here" check that didn't exist anywhere in
 * the codebase before this -- `ValidateLogin`/`AttemptLogin` only ever fire once, at sign-in, so
 * a person sanctioned mid-session would otherwise stay fully authenticated until their session
 * naturally expired. Appended to the "web" group in `ServiceProvider::boot()`, the same
 * registration `InjectPortal` already uses.
 */
class EnforceSanctions
{
    public function handle(Request $request, Closure $next)
    {
        $person = Auth::user();

        if ($person !== null && $person->isAccessBlocked()) {
            $sanction = Sanction::query()
                ->where('person_id', $person->id)
                ->whereNull('lifted_at')
                ->latest('issued_at')
                ->first();

            // Flashed, not persisted -- by the time the access-blocked page loads, the person is
            // a guest again, so a page refresh losing this and falling back to a generic message
            // is an accepted trade-off, not an oversight (see that view's own fallback).
            $request->session()->flash('access_blocked', [
                'reason' => $sanction?->reason?->value,
                'note' => $sanction?->note,
                'until' => $person->access_blocked_until,
            ]);

            Auth::logout();

            return redirect()->route('kopling-core::community/access-blocked');
        }

        return $next($request);
    }
}
