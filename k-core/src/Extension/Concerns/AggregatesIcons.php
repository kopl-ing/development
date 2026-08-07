<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extend\Icon;
use Kopling\Core\Extension\Contract\ChangesIcons;
use Kopling\Core\Extension\Contract\HasIcons;

trait AggregatesIcons
{
    /**
     * @return Collection<string, Icon>
     */
    public function icons(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['icons'])->map(fn (array $data) => Icon::fromArray($data))->keyBy('id');
        }

        $icons = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasIcons) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->icons())->ensure(Icon::class);

            foreach ($declared as $icon) {
                $icon->id = $prefix.$icon->id;

                $icons[$icon->id] = $icon;
            }
        }

        return collect($icons)->keyBy('id');
    }

    /**
     * @return array<string, string>
     */
    public function iconPackChoices(): array
    {
        $choices = [];

        foreach ($this->extensions() as $package => $extension) {
            if ($extension instanceof ChangesIcons) {
                $choices[$this->id($package)] = $extension::name();
            }
        }

        return $choices;
    }

    /**
     * @return Collection<string, array<string, string>>
     */
    public function iconPackMappings(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['iconPackMappings']);
        }

        $mappings = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesIcons) {
                continue;
            }

            $mappings[$this->id($package)] = $extension->iconMap();
        }

        return collect($mappings);
    }
}
