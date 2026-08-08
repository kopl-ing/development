<?php

declare(strict_types=1);

namespace Kopling\Moderation\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Moderation\ModerationReason;
use Kopling\Moderation\Flag;

class FlagController
{
    use AuthorizesRequests;

    /**
     * `auth` middleware only (see routes/community.php) -- reporting needs no permission by
     * design, only a signed-in session. `$type` must be a registered `Manager::
     * moderationTargets()` alias, checked here rather than trusting `Flag::resolveFlaggable()`
     * alone -- the morph map is a global Laravel registry another extension could also register
     * an alias into for its own unrelated purpose (e.g. `reactions`' own `reactable_type`
     * aliases), so resolving successfully there doesn't by itself mean this type was ever meant
     * to be flaggable.
     */
    public function store(Request $request, Manager $manager, string $type, string $id): RedirectResponse
    {
        abort_unless($manager->moderationTargets()->has($type), 404);

        $flaggable = Flag::resolveFlaggable($type, $id);

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(ModerationReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        Flag::updateOrCreate(
            [
                'flaggable_type' => $type,
                'flaggable_id' => $flaggable->getKey(),
                'person_id' => Auth::id(),
            ],
            [
                'reason' => $validated['reason'],
                'note' => $validated['note'] ?? null,
                'status' => Flag::STATUS_PENDING,
                'resolved_by' => null,
                'resolved_at' => null,
            ],
        );

        return back();
    }

    public function dismiss(Flag $flag): RedirectResponse
    {
        $this->authorize('kopling-moderation::moderate');

        $flag->update([
            'status' => Flag::STATUS_DISMISSED,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return back();
    }
}
