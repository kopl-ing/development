<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;

/**
 * Lists people and lets an operator assign them to Groups -- the one action Pin's own Groups
 * targeting needs a real UI for before it's usable end-to-end (see roadmap.md). Deliberately
 * not a general person editor: own email/password, avatar, etc. stay out of scope here (a
 * separate person detail/profile page, not yet planned).
 */
class PeopleController
{
    public function index(): View
    {
        return view('kopling-admin::people.index', [
            'people' => Person::with('groups')->orderBy('name')->get(),
            'groups' => Group::orderBy('name')->get(),
        ]);
    }

    /**
     * Syncs the full set of a person's group memberships in one call -- attach/detach, no
     * history, matching the `group_person` pivot's own no-extra-columns shape.
     */
    public function updateGroups(Request $request, Person $person): RedirectResponse
    {
        $person->groups()->sync($request->input('groups', []));

        return redirect()->route('kopling-admin::admin/people');
    }
}
