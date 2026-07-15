<?php

declare(strict_types=1);

namespace Kopling\Core\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kopling\Core\Extension\Manager;
use Kopling\Core\Settings\Settings;

/**
 * Resolves a semantic icon id (declared via `HasIcons::icons()`, e.g. "kopling-core::home") to
 * whichever concrete icon actually renders it: the active icon pack's own mapping when one
 * exists and covers this id (`ChangesIcons::iconMap()`), otherwise the `Icon::$default` Font
 * Awesome name every declared icon is required to carry -- so a pack with only partial coverage
 * never breaks a page, it just silently falls back to the plain Font Awesome look for whatever
 * it doesn't map.
 *
 * Always renders through the `svg()` helper (see the view), never a pack-prefixed
 * `<x-dynamic-component>` -- Blade Icons' own fallback chain (`Factory::svg()`) only runs
 * through that helper/the `@svg` directive; a `<x-fas-house/>`-style component tag that can't
 * resolve throws Laravel's own "component not found" error before Blade Icons' fallback logic
 * ever executes (confirmed against `BladeUI\Icons\Factory`'s own source, not just its docs).
 *
 * `SETTING` isn't written anywhere yet -- no admin picker UI exists yet (deliberately deferred,
 * see decisions.md), so `Settings::get()` always returns `null` today and every icon renders via
 * its Font Awesome default. The key is already the final, prefixed shape a future `HasAdminSettings`
 * `Select` field would use, so wiring that up later needs no change here.
 *
 * Unlike `Item`/`Link`/`Toggle` (`array $data`, dispatched generically through
 * `<x-dynamic-component :data="...">` by `UxEntry`/`Field`, which don't know a target
 * component's own prop names ahead of time), `Icon` is invoked directly by Blade authors
 * (`<x-k::icon name="kopling-core::home" />`) with exactly one well-known attribute -- the same
 * convention Blade Icons' own default `<x-icon name="camera" />` component uses.
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
