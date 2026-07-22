<?php

declare(strict_types=1);

namespace Kopling\Pages;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

/**
 * An admin-authored, reusable section layout -- full Blade source (`blade_source`), compiled via
 * Blade::render() at display time, plus a declared `slots` list ({name, type, label}) describing
 * what variables that source expects ($name in the template) and what input widget to collect
 * each as. See the `page_section_templates` migration for the security reasoning behind this
 * being gated by its own permission.
 */
class PageSectionTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'blade_source',
        'slots',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class, 'template_id');
    }
}
