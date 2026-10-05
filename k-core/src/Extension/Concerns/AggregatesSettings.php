<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Illuminate\Support\Collection;
use Kopling\Core\Extension\Contract\HasSettings;
use Kopling\Core\Ux\Form\Field;

trait AggregatesSettings
{
    /**
     * @return Collection<string, array<Field>>
     */
    public function settings(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['settings'] ?? [])
                ->map(fn (array $fields) => array_map(fn (array $data) => Field::fromArray($data), $fields));
        }

        $settings = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasSettings) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->settings())->ensure(Field::class);

            foreach ($declared as $field) {
                $field->id = $prefix.$field->id;
            }

            $settings[$this->id($package)] = $declared->all();
        }

        return collect($settings);
    }
}
