<?php

declare(strict_types=1);

namespace Kopling\Docs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Kopling\Core\Database\Model;

/**
 * The DB index over the drive `PageRegistry::sync()` reads from -- the drive stays the authored
 * source of truth (a `.md` file + front matter), this table only exists so a request can query
 * "the docs tree" without walking the filesystem on every hit.
 */
class DocPage extends Model
{
    use HasUuids;

    protected $table = 'docs_pages';

    protected $fillable = [
        'slug',
        'title',
        'section',
        'order',
        'locale',
        'storage_path',
        'content_hash',
        'content_html',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }
}
