<?php

declare(strict_types=1);

namespace Kopling\Pages;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

class Page extends Model
{
    use HasUuids;

    protected $fillable = [
        'path',
        'title',
        'meta_description',
        'published',
        'show_in_nav',
        'nav_order',
        'is_index',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'show_in_nav' => 'boolean',
            'nav_order' => 'integer',
            'is_index' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('order');
    }
}
