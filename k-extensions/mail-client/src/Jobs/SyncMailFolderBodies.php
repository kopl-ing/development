<?php

declare(strict_types=1);

namespace Kopling\MailClient\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kopling\MailClient\MailFolder;
use Kopling\MailClient\MailMessage;
use Kopling\MailClient\Support\MailboxFactory;
use Kopling\MailClient\Support\MessageMapper;

/**
 * Body-backfill pass for one folder -- newest-first, capped per run (BATCH_SIZE) rather than
 * draining the whole backlog in one job, per the "initial sync window" decision: someone
 * connecting a mailbox with years of history shouldn't have "connect mailbox" block on fetching
 * all of it. Deliberately doesn't re-dispatch itself for the next batch -- the next scheduled
 * SyncMailAccount cycle (1-2 min later) re-triggers this via a fresh SyncMailFolderHeaders run,
 * making progress across cycles without an unbounded self-requeue loop on a shared-hosting
 * worker.
 */
class SyncMailFolderBodies implements ShouldQueue
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

    private const BATCH_SIZE = 50;

    private const CHUNK_SIZE = 10;

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

        $pendingUids = MailMessage::query()
            ->where('mail_folder_id', $folder->id)
            ->whereNull('body_text')
            ->whereNull('body_html')
            ->orderByDesc('sent_at')
            ->limit(self::BATCH_SIZE)
            ->pluck('uid');

        if ($pendingUids->isEmpty()) {
            return;
        }

        $mailbox = $mailboxes->make($account);

        try {
            $imapFolder = $mailbox->folders()->find($folder->path);

            if ($imapFolder === null) {
                return;
            }

            $imapFolder->messages()
                ->uid($pendingUids->all())
                ->withHeaders()
                ->withBody()
                ->chunk(
                    fn ($messages) => $this->syncChunk($folder->id, $messages, $mapper),
                    chunkSize: self::CHUNK_SIZE,
                );
        } finally {
            $mailbox->disconnect();
        }
    }

    /**
     * @param  \DirectoryTree\ImapEngine\Collections\MessageCollection  $messages
     */
    private function syncChunk(string $folderId, $messages, MessageMapper $mapper): void
    {
        foreach ($messages as $message) {
            MailMessage::query()
                ->where('mail_folder_id', $folderId)
                ->where('uid', $message->uid())
                ->update($mapper->bodyAttributes($message));
        }
    }
}
