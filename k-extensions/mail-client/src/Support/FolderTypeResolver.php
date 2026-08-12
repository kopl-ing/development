<?php

declare(strict_types=1);

namespace Kopling\MailClient\Support;

use DirectoryTree\ImapEngine\FolderInterface;
use Kopling\MailClient\MailFolder;

/**
 * Maps an IMAP folder to one of MailFolder::TYPE_* -- what the unified inbox's smart views pool
 * across accounts by. "INBOX" is a reserved path name (RFC 9051 5.1), not a special-use flag, so
 * it's checked separately from the RFC 6154 SPECIAL-USE flags (\Sent, \Drafts, \Trash, \Archive,
 * \Junk) the rest of the mapping reads off `$folder->flags()`. A folder matching neither is
 * `null` -- provider-specific or person-made, only reachable via its own account's folder tree,
 * never one of the pooled smart views.
 */
class FolderTypeResolver
{
    /**
     * @var array<string, string>
     */
    private const FLAG_TYPES = [
        '\\Sent' => MailFolder::TYPE_SENT,
        '\\Drafts' => MailFolder::TYPE_DRAFTS,
        '\\Trash' => MailFolder::TYPE_TRASH,
        '\\Archive' => MailFolder::TYPE_ARCHIVE,
        '\\Junk' => MailFolder::TYPE_SPAM,
    ];

    public function resolve(FolderInterface $folder): ?string
    {
        if (strtoupper($folder->path()) === 'INBOX') {
            return MailFolder::TYPE_INBOX;
        }

        foreach ($folder->flags() as $flag) {
            if (isset(self::FLAG_TYPES[$flag])) {
                return self::FLAG_TYPES[$flag];
            }
        }

        return null;
    }
}
