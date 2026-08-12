<?php

declare(strict_types=1);

namespace Kopling\MailClient;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

class MailFolder extends Model
{
    use HasUuids;

    /**
     * Special-folder types the unified inbox's smart views ("All Inboxes", "Sent") pool across
     * every connected account by -- a folder with no matching special role (a provider-specific
     * one, a person-made one) is `null`/custom and only reachable via its own account's tree.
     */
    public const TYPE_INBOX = 'inbox';

    public const TYPE_SENT = 'sent';

    public const TYPE_DRAFTS = 'drafts';

    public const TYPE_TRASH = 'trash';

    public const TYPE_ARCHIVE = 'archive';

    public const TYPE_SPAM = 'spam';

    protected $fillable = [
        'mail_account_id',
        'name',
        'path',
        'type',
        'uidvalidity',
        'message_count',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }
}
