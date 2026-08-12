<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Folder;
use DirectoryTree\ImapEngine\Mailbox;
use Kopling\MailClient\MailFolder;
use Kopling\MailClient\Support\FolderTypeResolver;

function imapFolder(string $path, array $flags = []): Folder
{
    return new Folder(Mailbox::make(), $path, $flags, '/');
}

it('resolves INBOX by its reserved path name, case-insensitively', function () {
    $resolver = new FolderTypeResolver;

    expect($resolver->resolve(imapFolder('INBOX')))->toBe(MailFolder::TYPE_INBOX)
        ->and($resolver->resolve(imapFolder('inbox')))->toBe(MailFolder::TYPE_INBOX);
});

it('resolves special-use folders by their RFC 6154 flag', function () {
    $resolver = new FolderTypeResolver;

    expect($resolver->resolve(imapFolder('Sent Items', ['\\Sent'])))->toBe(MailFolder::TYPE_SENT)
        ->and($resolver->resolve(imapFolder('Drafts', ['\\Drafts'])))->toBe(MailFolder::TYPE_DRAFTS)
        ->and($resolver->resolve(imapFolder('Trash', ['\\Trash'])))->toBe(MailFolder::TYPE_TRASH)
        ->and($resolver->resolve(imapFolder('Archive', ['\\Archive'])))->toBe(MailFolder::TYPE_ARCHIVE)
        ->and($resolver->resolve(imapFolder('Spam', ['\\Junk'])))->toBe(MailFolder::TYPE_SPAM);
});

it('returns null for a folder matching no known type', function () {
    expect((new FolderTypeResolver)->resolve(imapFolder('Some Custom Folder', ['\\HasNoChildren'])))->toBeNull();
});
