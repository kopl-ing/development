<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Folder;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\Message;
use Kopling\MailClient\Support\MessageMapper;

/*
 * Constructs a real ImapEngine Message from raw head/body strings -- Message's own constructor
 * takes exactly those, so this needs no live IMAP connection, just like the sync jobs' own
 * MessageMapper collaborator this file is testing.
 */
function imapMessage(string $head, string $body = '', array $flags = [], int $uid = 42): Message
{
    $folder = new Folder(Mailbox::make(), 'INBOX', [], '/');

    return new Message($folder, $uid, $flags, $head, $body);
}

it('maps header attributes from a parsed message', function () {
    $head = implode("\r\n", [
        'From: Ada Lovelace <ada@example.test>',
        'To: Bob Babbage <bob@example.test>',
        'Cc: Charlie Babbage <charlie@example.test>',
        'Subject: Hello there',
        'Date: Mon, 1 Jan 2024 12:00:00 +0000',
        'Message-ID: <abc123@example.test>',
        'In-Reply-To: <parent456@example.test>',
        'Content-Type: text/plain; charset=utf-8',
    ]);

    $attributes = (new MessageMapper)->headerAttributes(imapMessage($head));

    expect($attributes['uid'])->toBe(42)
        ->and($attributes['subject'])->toBe('Hello there')
        ->and($attributes['from_name'])->toBe('Ada Lovelace')
        ->and($attributes['from_address'])->toBe('ada@example.test')
        ->and($attributes['to'])->toBe([['name' => 'Bob Babbage', 'email' => 'bob@example.test']])
        ->and($attributes['cc'])->toBe([['name' => 'Charlie Babbage', 'email' => 'charlie@example.test']])
        ->and($attributes['message_id'])->toContain('abc123@example.test')
        ->and($attributes['in_reply_to'])->toContain('parent456@example.test')
        ->and($attributes['sent_at'])->not->toBeNull();
});

it('maps body attributes, including a length-capped snippet', function () {
    $head = implode("\r\n", [
        'From: Ada <ada@example.test>',
        'Content-Type: text/plain; charset=utf-8',
    ]);
    $body = str_repeat('word ', 60);

    $attributes = (new MessageMapper)->bodyAttributes(imapMessage($head, $body));

    expect($attributes['body_text'])->toContain('word')
        ->and(mb_strlen($attributes['snippet']))->toBeLessThanOrEqual(163)
        ->and($attributes['has_attachments'])->toBeFalse();
});

it('maps flag attributes off the message envelope flags', function () {
    $flags = (new MessageMapper)->flagAttributes(imapMessage("Subject: x\r\n", flags: ['\\Seen', '\\Flagged']));

    expect($flags)->toBe([
        'seen' => true,
        'flagged' => true,
        'answered' => false,
        'draft' => false,
        'deleted' => false,
    ]);
});
