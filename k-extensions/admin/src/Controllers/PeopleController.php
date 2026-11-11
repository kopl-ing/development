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
    public function index(Request $request): View
    {
        $groups = Group::orderBy('name')->get();
        $withEmail = $request->boolean('with_email', true);
        $selectedGroups = $groups->pluck('id')
            ->filter(fn ($id) => $request->collect('groups')->contains($id))
            ->values();

        return view('kopling-admin::people.index', [
            'people' => Person::with('groups')
                ->when($withEmail, fn ($query) => $query->whereNotNull('email'))
                ->when($selectedGroups->isNotEmpty(), fn ($query) => $query->whereHas(
                    'groups',
                    fn ($groupQuery) => $groupQuery->whereKey($selectedGroups),
                ))
                ->orderBy('name')
                ->get(),
            'groups' => $groups,
            'withEmail' => $withEmail,
            'selectedGroups' => $selectedGroups,
        ]);
    }

    /**
     * Syncs the full set of a person's group memberships in one call -- attach/detach, no
     * history, matching the `group_person` pivot's own no-extra-columns shape.
     */
    public function updateGroups(Request $request, Person $person): RedirectResponse
    {
        $person->groups()->sync($request->input('groups', []));

        return back(fallback: route('kopling-admin::admin/people'));
    }

    /**
     * Everything the person created goes with them, through the foreign keys' cascades.
     */
    public function destroy(Request $request, Person $person): RedirectResponse
    {
        abort_if($person->is($request->user()), 403);

        $person->delete();

        return back(fallback: route('kopling-admin::admin/people'));
    }
}
