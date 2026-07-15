<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\People\Group;

/**
 * Plain create/rename/delete for Groups -- the data model (`groups` + `group_person`, no extra
 * columns) already supports this trivially, nothing hung an admin action off it yet.
 */
class GroupsController
{
    public function index(): View
    {
        return view('kopling-admin::groups.index', [
            'groups' => Group::withCount('people')->orderBy('name')->get(),
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
}
