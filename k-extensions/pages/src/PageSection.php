<?php

declare(strict_types=1);

namespace Kopling\Pages;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class PageSection extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_id',
        'template_id',
        'order',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'data' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PageSectionTemplate::class, 'template_id');
    }
}
