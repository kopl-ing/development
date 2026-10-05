<?php

declare(strict_types=1);

namespace Kopling\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Settings\PersonSettings;
use Kopling\Core\Ux\ComponentTag;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\Toggle;

class AccountSettingsController
{
    /**
     * Extensions add their own section here (a card with its own form and route), bound to the signed-in Person.
     */
    public const SECTIONS_SLOT = 'kopling-core::settings.sections';

    public function edit(Request $request, Manager $manager): View
    {
        $this->authorizeLocal($request);

        $stored = PersonSettings::all($request->user());

        return view('kopling-core::community.settings', [
            'person' => $request->user(),
            'preferences' => $manager->settings()->flatten(1)->map(fn (Field $field) => [
                'field' => $field,
                'value' => $stored[$field->id] ?? $field->default,
            ]),
        ]);
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
     * Only writes a field this request submitted, same as admin's settings form.
     */
    public function updatePreferences(Request $request, Manager $manager): RedirectResponse
    {
        $this->authorizeLocal($request);

        $fields = $manager->settings()->flatten(1)->filter(fn (Field $field) => $request->has($field->id));

        $request->validate($fields->mapWithKeys(fn (Field $field) => [$field->id => $this->rules($field)])->all());

        $fields->each(fn (Field $field) => PersonSettings::set($request->user(), $field->id, $request->input($field->id)));

        return redirect()->route('kopling-core::community/settings')
            ->with('preferences_status', __('kopling-core::community.settings_saved'));
    }

    /**
     * @return array<int, mixed>
     */
    protected function rules(Field $field): array
    {
        if ($field->component === ComponentTag::resolve(Toggle::class)) {
            return ['required', Rule::in(['0', '1'])];
        }

        if (isset($field->data['options'])) {
            return ['required', Rule::in(array_map('strval', array_keys($field->data['options'])))];
        }

        return ['nullable', 'string', 'max:1000'];
    }

    /**
     * A remote person (any non-null `origin`) is canonical on their own instance, so it's edited there, not here.
     */
    private function authorizeLocal(Request $request): void
    {
        abort_unless($request->user()->isLocal(), 404);
    }
}
