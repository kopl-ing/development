<?php

declare(strict_types=1);

namespace Kopling\MailClient;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class MailMessageFlag extends Model
{
    use HasUuids;

    protected $fillable = [
        'mail_message_id',
        'seen',
        'flagged',
        'answered',
        'draft',
        'deleted',
        'dirty',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'seen' => 'boolean',
            'flagged' => 'boolean',
            'answered' => 'boolean',
            'draft' => 'boolean',
            'deleted' => 'boolean',
            'dirty' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
}
