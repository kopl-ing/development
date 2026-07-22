<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Settings\Settings;

/**
 * Lets an admin override which path a Portal is actually registered at -- e.g. freeing up `/`
 * for a different Portal than whichever declares it as its own default (Community, by default).
 * `id` travels in the POST body rather than as a route-bound model, same reasoning as
 * `StorageMappingsController`: a Portal id already contains "::", not worth fighting route
 * segment encoding for.
 */
class PortalsController
{
    public function index(Manager $manager): View
    {
        return view('kopling-admin::portals.index', [
            'portals' => $manager->portals()->sortBy('id')->values(),
        ]);
    }

    /**
     * Validated against every *other* portal's current effective path, not just declared
     * defaults -- fails loud here, at save time, rather than letting two portals silently
     * resolve to the same route prefix (see `Manager::applyPortalPathOverrides()`).
     */
    public function update(Request $request, Manager $manager): RedirectResponse
    {
        $id = (string) $request->input('id');
        $portal = $manager->portals()->get($id);

        abort_if($portal === null, 404);

        $path = trim((string) $request->input('path', ''), '/');

        $conflict = $manager->portals()
            ->reject(fn (Portal $p) => $p->id === $id)
            ->first(fn (Portal $p) => $p->path === $path);

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'path' => __('kopling-admin::messages.portal_path_conflict', ['portal' => $conflict->label]),
            ]);
        }

        Settings::set("core.portal_path.$id", $path);

        return redirect()->route('kopling-admin::admin/portals');
    }

    public function reset(Request $request): RedirectResponse
    {
        Settings::forget('core.portal_path.'.$request->input('id'));

        return redirect()->route('kopling-admin::admin/portals');
    }
}
