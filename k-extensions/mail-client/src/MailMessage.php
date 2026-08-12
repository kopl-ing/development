<?php

declare(strict_types=1);

namespace Kopling\MailClient;

use Illuminate\Database\Eloquent\Builder;
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
        'references',
        'thread_id',
        'subject',
        'from_name',
        'from_address',
        'to',
        'cc',
        'bcc',
        'snippet',
        'body_text',
        'body_html',
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
            'references' => 'array',
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

    /**
     * Collapses any scoped mail_messages query down to one row per thread_id -- that thread's
     * own latest message, plus a `thread_message_count` for how many messages it actually has.
     * What the unified inbox's card list is built from: one card per conversation, not one per
     * individual message. $query's own where/whereHas scoping (account ownership, folder type,
     * etc.) is preserved -- only the projection changes.
     */
    public static function latestPerThread(Builder $query): Builder
    {
        $latest = (clone $query)
            ->selectRaw('thread_id, MAX(sent_at) as latest_sent_at, COUNT(*) as thread_message_count')
            ->groupBy('thread_id');

        return static::query()
            ->joinSub($latest, 'thread_latest', function ($join) {
                $join->on('mail_messages.thread_id', '=', 'thread_latest.thread_id')
                    ->on('mail_messages.sent_at', '=', 'thread_latest.latest_sent_at');
            })
            ->addSelect('mail_messages.*', 'thread_latest.thread_message_count');
    }

    /**
     * Every message in the same conversation as this one, oldest first -- scoped to accounts
     * owned by $personId so a thread two of the person's own accounts both happen to be on
     * (a shared mailing list, a CC'd colleague) never pulls in a message belonging to someone
     * else's mailbox, even though thread_id itself is derived from globally-unique Message-IDs.
     */
    public static function inThread(string $threadId, string $personId): Builder
    {
        return static::query()
            ->where('thread_id', $threadId)
            ->whereHas('account', fn (Builder $query) => $query->where('person_id', $personId))
            ->with(['account', 'flags'])
            ->orderBy('sent_at');
    }
}
