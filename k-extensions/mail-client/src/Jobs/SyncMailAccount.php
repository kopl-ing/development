<?php

declare(strict_types=1);

namespace Kopling\MailClient\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kopling\MailClient\MailAccount;
use Kopling\MailClient\MailFolder;
use Kopling\MailClient\Support\FolderTypeResolver;
use Kopling\MailClient\Support\MailboxFactory;

/**
 * Entry point for syncing one mailbox -- dispatched immediately after a person connects an
 * account (AccountsController::store()) and periodically for every account by the cron-fallback
 * command (Console\SyncPendingCommand) on hosts with no real queue worker. Discovers folders and
 * detects a UIDVALIDITY reset per folder, then hands each folder off to its own
 * SyncMailFolderHeaders job -- kept separate so one folder's fetch can't block, or need retrying
 * independently of, another's, and so each queued unit of work stays short enough for a
 * shared-hosting worker's own time limit.
 */
class SyncMailAccount implements ShouldQueue
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

    /**
     * A `syncing_since` older than this is treated as abandoned (a worker that died mid-job),
     * not genuinely in flight -- self-heals without manual intervention, same reasoning as
     * ActivitypubDelivery's own retry-recency window.
     */
    private const STUCK_AFTER_MINUTES = 10;

    public function __construct(public string $mailAccountId)
    {
    }

    public function handle(MailboxFactory $mailboxes, FolderTypeResolver $folderTypes): void
    {
        $account = MailAccount::find($this->mailAccountId);

        if ($account === null) {
            return;
        }

        if ($account->syncing_since !== null && $account->syncing_since->gt(now()->subMinutes(self::STUCK_AFTER_MINUTES))) {
            return;
        }

        $account->forceFill(['syncing_since' => now()])->save();

        $mailbox = $mailboxes->make($account);

        try {
            foreach ($mailbox->folders()->get() as $imapFolder) {
                if (in_array('\\Noselect', $imapFolder->flags(), true)) {
                    continue;
                }

                $status = $imapFolder->status();
                $uidvalidity = (int) ($status['UIDVALIDITY'] ?? 0);

                $existing = MailFolder::query()
                    ->where('mail_account_id', $account->id)
                    ->where('path', $imapFolder->path())
                    ->first();

                // A changed UIDVALIDITY means the server invalidated this folder's UID
                // numbering -- every locally stored UID for it is now meaningless, so the only
                // correct move is to drop and fully re-sync, not merge against stale data.
                if ($existing?->uidvalidity !== null && $existing->uidvalidity !== $uidvalidity) {
                    $existing->messages()->delete();
                }

                $folder = MailFolder::query()->updateOrCreate(
                    ['mail_account_id' => $account->id, 'path' => $imapFolder->path()],
                    [
                        'name' => $imapFolder->name(),
                        'type' => $folderTypes->resolve($imapFolder),
                        'uidvalidity' => $uidvalidity,
                        'message_count' => (int) ($status['MESSAGES'] ?? 0),
                    ],
                );

                SyncMailFolderHeaders::dispatch($folder->id);
            }

            $account->forceFill([
                'last_synced_at' => now(),
                'last_sync_error' => null,
                'syncing_since' => null,
            ])->save();
        } catch (\Throwable $e) {
            $account->forceFill([
                'last_sync_error' => $e->getMessage(),
                'syncing_since' => null,
            ])->save();

            throw $e;
        } finally {
            $mailbox->disconnect();
        }
    }
}
