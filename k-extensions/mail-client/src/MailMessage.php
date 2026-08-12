<?php

declare(strict_types=1);

namespace Kopling\MailClient;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Kopling\Core\Database\Model;

class MailMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'mail_account_id',
        'mail_folder_id',
        'uid',
        'message_id',
        'in_reply_to',
        'subject',
        'from_name',
        'from_address',
        'to',
        'cc',
        'bcc',
        'snippet',
        'sent_at',
        'has_attachments',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'sent_at' => 'datetime',
            'has_attachments' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'mail_folder_id');
    }

    public function flags(): HasOne
    {
        return $this->hasOne(MailMessageFlag::class);
    }
}
