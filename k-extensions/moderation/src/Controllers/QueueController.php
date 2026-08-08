<?php

declare(strict_types=1);

namespace Kopling\Moderation\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Person;
use Kopling\Core\Ux\Context;
use Kopling\Moderation\Flag;

class QueueController
{
    use AuthorizesRequests;

    /**
     * `moderate` also gates the whole Moderation portal route group (see `Extension::portals()`)
     * -- authorized again here for the same defense-in-depth reason every other controller in
     * this codebase re-checks its own permission alone.
     *
     * The "sanctioned" tab is a different underlying model entirely (`Person`, not `Flag` --
     * currently-restricted people, not reports), so it's a separate action/view rather than
     * forcing one template to render two structurally different row shapes.
     */
    public function index(Request $request, Manager $manager): View
    {
        $this->authorize('kopling-moderation::moderate');

        $status = $request->query('status', Flag::STATUS_PENDING);

        if ($status === 'sanctioned') {
            return $this->sanctioned();
        }

        $query = Flag::query()
            ->whereIn('flaggable_type', $manager->moderationTargets()->keys())
            ->where('status', $status)
            // withoutGlobalScopes() on the MorphTo relation itself (not a plain Builder) --
            // MorphTo::__call() specifically buffers this for per-type replay (see its own
            // source), so it safely bypasses both SoftDeletingScope (a hidden flaggable) and
            // AuthorVisibilityScope (a flaggable authored by a shadowbanned person) together,
            // for every morphed type uniformly -- a harmless no-op for a type with neither, like
            // Person. Without it, either state would silently vanish from its own queue row,
            // defeating the point of a moderator being able to review it at all.
            ->with(['flaggable' => fn ($morphTo) => $morphTo->withoutGlobalScopes(), 'person', 'resolvedBy'])
            ->latest();

        return view('kopling-moderation::queue.index', [
            'context' => new Context(subject: $query),
            'status' => $status,
            'targets' => $manager->moderationTargets(),
        ]);
    }

    protected function sanctioned(): View
    {
        $query = Person::query()
            ->where(fn ($q) => $q
                ->whereNotNull('communication_blocked_at')
                ->orWhere('visibility', 'hidden')
                ->orWhereNotNull('access_blocked_at'))
            ->latest('updated_at');

        return view('kopling-moderation::queue.sanctioned', [
            'context' => new Context(subject: $query),
            'status' => 'sanctioned',
        ]);
    }
}
