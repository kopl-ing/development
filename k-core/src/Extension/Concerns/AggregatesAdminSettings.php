<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Ux\Form\Field;

trait AggregatesAdminSettings
{
    /**
     * @return Collection<string, array<Field>>
     */
    public function adminSettings(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['adminSettings'])
                ->map(fn (array $fields) => array_map(fn (array $data) => Field::fromArray($data), $fields));
        }

        $settings = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasAdminSettings) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->adminSettings())->ensure(Field::class);

            foreach ($declared as $field) {
                $field->id = $prefix.$field->id;
            }

            $settings[$this->id($package)] = $declared->all();
        }

        return collect($settings);
    }
}
