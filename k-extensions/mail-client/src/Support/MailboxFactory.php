<?php

declare(strict_types=1);

namespace Kopling\MailClient\Support;

use DirectoryTree\ImapEngine\Mailbox;
use Kopling\MailClient\MailAccount;

/**
 * Builds a configured, not-yet-connected `Mailbox` for a `MailAccount` -- connection itself
 * happens lazily on first use (`Mailbox::connect()`), same as the library's own default.
 */
class MailboxFactory
{
    public function make(MailAccount $account): Mailbox
    {
        return Mailbox::make([
            'host' => $account->incoming_host,
            'port' => $account->incoming_port,
            // The library treats an empty string as "no encryption" (falls back to a plain
            // 'tcp' transport) -- our own 'none' enum value has no meaning to it directly.
            'encryption' => $account->incoming_encryption === 'none' ? '' : $account->incoming_encryption,
            'username' => $account->username,
            'password' => $account->password,
            'authentication' => $account->auth_type === 'oauth' ? 'oauth' : 'plain',
        ]);
    }
}
