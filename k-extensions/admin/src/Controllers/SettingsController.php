<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Settings\Settings;
use Kopling\Core\Ux\Form\Field;

class SettingsController
{
    /**
     * One section per extension that declared `HasAdminSettings::adminSettings()`, each field
     * paired with its current persisted value (falling back to the field's own `$default`) --
     * the extension only ever declares shape, never a stored value, see `Field`'s own docblock.
     */
    public function index(Manager $manager): View
    {
        $names = collect($manager->extensions())
            ->mapWithKeys(fn ($extension, $package) => [$manager->id($package) => $extension::name()]);

        $sections = $manager->adminSettings()
            ->filter(fn (array $fields) => $fields !== [])
            ->map(fn (array $fields, string $id) => [
                'label' => $names->get($id, $id),
                'fields' => collect($fields)->map(fn (Field $field) => [
                    'field' => $field,
                    'value' => Settings::get($field->id, $field->default),
                ]),
            ]);

        return view('kopling-admin::settings.index', ['sections' => $sections]);
    }

    /**
     * One form, one Save button, for every declared field at once -- not per-field autosave.
     * Only ever writes a field this request actually submitted a value for, so a field a
     * `HasAdminSettings` implementor stops declaring between requests is simply never touched.
     */
    public function store(Request $request, Manager $manager): RedirectResponse
    {
        $manager->adminSettings()->flatten(1)->each(function (Field $field) use ($request) {
            if (! $request->has($field->id)) {
                return;
            }

            Settings::set($field->id, $request->input($field->id));
        });

        return redirect()->route('kopling-admin::admin/settings');
    }
}
