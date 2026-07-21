<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Settings\Settings;

/**
 * Resolves a semantic icon id to whichever concrete icon actually renders it: the active icon
 * pack's mapping when it covers this id, otherwise the `Icon::$default` Font Awesome name every
 * declared icon carries -- a pack with partial coverage never breaks a page. Always renders
 * through the `svg()` helper, never a pack-prefixed `<x-dynamic-component>` -- Blade Icons' own
 * fallback chain only runs through that helper. `SETTING` isn't written anywhere yet (no admin
 * picker UI exists), so every icon currently renders via its Font Awesome default.
 */
class Icon extends Component
{
    protected const SETTING = 'kopling-core::icon-pack';

    public function __construct(public string $name)
    {
    }

    public function render(): View
    {
        return view('kopling-core::ux.icon', [
            'icon' => $this->resolveIcon($this->name),
        ]);
    }

    protected function resolveIcon(string $id): string
    {
        $manager = app(Manager::class);
        $pack = Settings::get(self::SETTING);

        if (is_string($pack) && $pack !== '') {
            $mapped = $manager->iconPackMappings()->get($pack)[$id] ?? null;

            if ($mapped !== null) {
                return $mapped;
            }
        }

        $declared = $manager->icons()->get($id);

        if ($declared === null) {
            throw new \InvalidArgumentException("Unknown icon [{$id}] -- no HasIcons extension declared it.");
        }

        return $declared->default;
    }
}
