<?php

declare(strict_types=1);

namespace Kopling\MailClient\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Kopling\MailClient\MailFolder;
use Kopling\MailClient\MailMessage;
use Kopling\MailClient\MailMessageFlag;
use Kopling\MailClient\Support\MailboxFactory;
use Kopling\MailClient\Support\MessageMapper;

/**
 * Headers-only pass over one folder (SyncMailAccount dispatches one of these per folder it
 * discovers) -- fast, gives the message list/folder tree something to show immediately, before
 * the slower body-backfill pass (SyncMailFolderBodies, dispatched once this completes) has
 * touched anything. Bulk `upsert()` rather than one query per message: naturally idempotent
 * (re-running just re-upserts the same rows, the folder's own unique(mail_folder_id, uid) index
 * prevents duplicates) and scales to a folder with thousands of messages without thousands of
 * round trips.
 */
class SyncMailFolderHeaders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 600];

    private const CHUNK_SIZE = 200;

    public function __construct(public string $mailFolderId)
    {
    }

    public function handle(MailboxFactory $mailboxes, MessageMapper $mapper): void
    {
        $folder = MailFolder::find($this->mailFolderId);

        if ($folder === null) {
            return;
        }

        $account = $folder->account;

        if ($account === null) {
            return;
        }

        $mailbox = $mailboxes->make($account);

        try {
            $imapFolder = $mailbox->folders()->find($folder->path);

            if ($imapFolder === null) {
                return;
            }

            $imapFolder->messages()
                ->withHeaders()
                ->withFlags()
                ->chunk(
                    fn ($messages) => $this->syncChunk($folder->id, $account->id, $messages, $mapper),
                    chunkSize: self::CHUNK_SIZE,
                );

            SyncMailFolderBodies::dispatch($folder->id);
        } finally {
            $mailbox->disconnect();
        }
    }

    /**
     * @param  \DirectoryTree\ImapEngine\Collections\MessageCollection  $messages
     */
    private function syncChunk(string $folderId, string $accountId, $messages, MessageMapper $mapper): void
    {
        if ($messages->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($messages as $message) {
            $attributes = $mapper->headerAttributes($message);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'mail_account_id' => $accountId,
                'mail_folder_id' => $folderId,
                'uid' => $attributes['uid'],
                'message_id' => $attributes['message_id'],
                'in_reply_to' => $attributes['in_reply_to'],
                'references' => json_encode($attributes['references']),
                'thread_id' => $attributes['thread_id'],
                'subject' => $attributes['subject'],
                'from_name' => $attributes['from_name'],
                'from_address' => $attributes['from_address'],
                'to' => json_encode($attributes['to']),
                'cc' => json_encode($attributes['cc']),
                'bcc' => json_encode($attributes['bcc']),
                'sent_at' => $attributes['sent_at']?->toDateTimeString(),
                'size' => $attributes['size'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // created_at deliberately excluded from the update list -- an existing row keeps its
        // original sync-first-seen timestamp rather than being reset on every re-sync.
        MailMessage::query()->upsert($rows, ['mail_folder_id', 'uid'], [
            'message_id', 'in_reply_to', 'references', 'thread_id', 'subject', 'from_name', 'from_address',
            'to', 'cc', 'bcc', 'sent_at', 'size', 'updated_at',
        ]);

        // Bulk upsert doesn't return generated/matched ids -- re-select by the same (folder, uid)
        // pairs to get the real mail_message_id each flags row needs to reference.
        $ids = MailMessage::query()
            ->where('mail_folder_id', $folderId)
            ->whereIn('uid', array_column($rows, 'uid'))
            ->pluck('id', 'uid');

        $flagRows = [];

        foreach ($messages as $message) {
            $mailMessageId = $ids[$message->uid()] ?? null;

            if ($mailMessageId === null) {
                continue;
            }

            $flags = $mapper->flagAttributes($message);

            $flagRows[] = [
                'id' => (string) Str::uuid(),
                'mail_message_id' => $mailMessageId,
                'seen' => $flags['seen'],
                'flagged' => $flags['flagged'],
                'answered' => $flags['answered'],
                'draft' => $flags['draft'],
                'deleted' => $flags['deleted'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($flagRows !== []) {
            MailMessageFlag::query()->upsert($flagRows, ['mail_message_id'], [
                'seen', 'flagged', 'answered', 'draft', 'deleted', 'updated_at',
            ]);
        }
    }
}
