<?php

declare(strict_types=1);

namespace Kopling\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Extension\RegistrationCache;
use Kopling\Core\Settings\EnabledExtensions;
use Kopling\Core\Settings\Settings;
use Kopling\Core\Ux\Form\Field;

class SettingsController
{
    /**
     * One card per installed extension (`extensions(includeDisabled: true)` -- unlike the old
     * settings-only listing, disabled extensions still get a card, styled accordingly), each
     * carrying its icon, enabled state, and (if it declared `HasAdminSettings::adminSettings()`)
     * its fields paired with their current persisted value.
     */
    public function index(Manager $manager): View
    {
        $extensions = collect($manager->extensions(includeDisabled: true))
            ->map(fn (AbstractExtension $extension, string $package) => $this->cardData($manager, $package, $extension))
            ->values();

        return view('kopling-admin::settings.index', ['extensions' => $extensions]);
    }

    /**
     * One form, one Save button, for every declared field at once -- not per-field autosave.
     * Only ever writes a field this request actually submitted a value for, so a field a
     * `HasAdminSettings` implementor stops declaring between requests is simply never touched.
     * Deliberately separate from `toggle()` below: enabling/disabling an extension is an
     * immediate action, not a pending field edit sitting in this form.
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

    /**
     * Flips one extension's enabled state and clears `RegistrationCache` so the change is live
     * on the very next request (matching the cache's own documented intent -- see its docblock).
     * Refuses `CannotBeDisabled` extensions server-side too, never trusting the UI having hidden
     * their button. Returns just the re-rendered card for htmx to swap in place.
     */
    public function toggle(Manager $manager, RegistrationCache $cache, string $id): View
    {
        $extensions = $manager->extensions(includeDisabled: true);

        $package = null;

        foreach (array_keys($extensions) as $candidate) {
            if ($manager->id($candidate) === $id) {
                $package = $candidate;
                break;
            }
        }

        abort_if($package === null, 404);

        $extension = $extensions[$package];

        abort_if($extension instanceof CannotBeDisabled, 403);

        $allIds = array_map(fn (string $p) => $manager->id($p), array_keys($extensions));

        EnabledExtensions::isEnabled($id)
            ? EnabledExtensions::disable($id, $allIds)
            : EnabledExtensions::enable($id, $allIds);

        $cache->clear();

        return view('kopling-admin::components.settings.partials.card', [
            'extension' => $this->cardData($manager, $package, $extension),
        ]);
    }

    /**
     * @return array{id: string, name: string, description: string, iconLg: ?string, iconSm: ?string, enabled: bool, cannotBeDisabled: bool, fields: \Illuminate\Support\Collection}
     */
    protected function cardData(Manager $manager, string $package, AbstractExtension $extension): array
    {
        $id = $manager->id($package);
        $cannotBeDisabled = $extension instanceof CannotBeDisabled;

        $fields = collect($manager->adminSettings()->get($id, []))
            ->map(fn (Field $field) => [
                'field' => $field,
                'value' => Settings::get($field->id, $field->default),
            ]);

        return [
            'id' => $id,
            'name' => $extension::name(),
            'description' => $extension::description(),
            'iconLg' => $manager->iconUrl($package, 'lg'),
            'iconSm' => $manager->iconUrl($package, 'sm'),
            'enabled' => $cannotBeDisabled || EnabledExtensions::isEnabled($id),
            'cannotBeDisabled' => $cannotBeDisabled,
            'fields' => $fields,
        ];
    }
}
