<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Group;

/**
 * Plain create/rename/delete for Groups -- the data model (`groups` + `group_person`, no extra
 * columns) already supports this trivially, nothing hung an admin action off it yet.
 */
class GroupsController
{
    public function index(Manager $manager): View
    {
        return view('kopling-admin::groups.index', [
            'groups' => Group::with('permissions')->withCount('people')->orderBy('name')->get(),
            'permissions' => $this->grantablePermissions($manager),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Group::create(['name' => $request->input('name')]);

        return redirect()->route('kopling-admin::admin/groups');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->update(['name' => $request->input('name')]);

        return redirect()->route('kopling-admin::admin/groups');
    }

    /**
     * `group_person`/`group_permission` rows cascade via their own FK constraints -- no manual
     * cleanup needed here.
     */
    public function destroy(Group $group): RedirectResponse
    {
        $group->delete();

        return redirect()->route('kopling-admin::admin/groups');
    }

    /**
     * Full delete-then-recreate rather than a diff -- `group_permission` has no extra columns
     * to preserve (same "no history" reasoning `PeopleController::updateGroups()` already
     * documents for its own `sync()` call), it's just not a `BelongsToMany`, so there's no
     * `sync()` to reach for here.
     */
    public function updatePermissions(Request $request, Group $group): RedirectResponse
    {
        $group->permissions()->delete();

        collect($request->input('permissions', []))->each(
            fn (string $permission) => $group->givePermissionTo($permission)
        );

        return redirect()->route('kopling-admin::admin/groups');
    }

    /**
     * Excludes anything `default`/`allowsGuests` -- neither is ever actually decided by a
     * Group's own grant (see `ServiceProvider`'s `Gate::define()` closure: `default` short-
     * circuits to always-true, `allowsGuests` only ever checks `$person instanceof Guest`), so
     * listing them here would let an admin "grant" something that structurally can't do
     * anything.
     *
     * @return Collection<string, string>
     */
    protected function grantablePermissions(Manager $manager): Collection
    {
        return collect($manager->permissions())
            ->reject(fn ($permission) => $permission->default || $permission->allowsGuests)
            ->pluck('label', 'id');
    }
}
