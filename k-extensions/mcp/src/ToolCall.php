<?php

declare(strict_types=1);

namespace Kopling\Mcp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;
use Laravel\Passport\Client;

class ToolCall extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const OK = 'ok';

    public const DENIED = 'denied';

    public const ERROR = 'error';

    protected $table = 'mcp_tool_calls';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'person_id',
        'client_id',
        'tool',
        'team_id',
        'arguments',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
