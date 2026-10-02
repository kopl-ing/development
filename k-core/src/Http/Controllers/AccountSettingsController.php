<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountSettingsController
{
    /**
     * Extensions add their own section here (a card with its own form and route), bound to the signed-in Person.
     */
    public const SECTIONS_SLOT = 'kopling-core::settings.sections';

    public function edit(Request $request): View
    {
        $this->authorizeLocal($request);

        return view('kopling-core::community.settings', ['person' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeLocal($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update(['name' => $data['name']]);

        return redirect()->route('kopling-core::community/settings')
            ->with('status', __('kopling-core::community.settings_saved'));
    }

    /**
     * A remote person (any non-null `origin`) is canonical on their own instance, so it's edited there, not here.
     */
    private function authorizeLocal(Request $request): void
    {
        abort_unless($request->user()->isLocal(), 404);
    }
}
