<?php

declare(strict_types=1);

namespace Kopling\MailClient\Support;

use DirectoryTree\ImapEngine\Address;
use DirectoryTree\ImapEngine\Message;
use Illuminate\Support\Str;

/**
 * Pure translation from an ImapEngine `Message` to `mail_messages`/`mail_message_flags` column
 * values -- kept out of the sync jobs themselves so it's unit-testable against a `Message`
 * constructed directly from raw head/body strings, no live IMAP connection required.
 */
class MessageMapper
{
    /**
     * Headers-only attributes -- safe to call after a `.withHeaders()` (no body) fetch. Deliberately
     * excludes `has_attachments`: that can only be determined accurately once the body is parsed
     * (see bodyAttributes()), not from headers alone.
     *
     * @return array<string, mixed>
     */
    public function headerAttributes(Message $message): array
    {
        $from = $message->from();

        return [
            'uid' => $message->uid(),
            'message_id' => $message->messageId(),
            'in_reply_to' => $message->inReplyTo()[0] ?? null,
            'subject' => $message->subject(),
            'from_name' => $from?->name(),
            'from_address' => $from?->email(),
            'to' => $this->addressesToArray($message->to()),
            'cc' => $this->addressesToArray($message->cc()),
            'bcc' => $this->addressesToArray($message->bcc()),
            'sent_at' => $message->date(),
            'size' => $message->size(),
        ];
    }

    /**
     * Body-pass attributes -- only safe once the message was fetched with `.withHeaders()->withBody()`
     * together (parsing text/html needs both: MIME boundaries/encoding live in the headers).
     *
     * @return array<string, mixed>
     */
    public function bodyAttributes(Message $message): array
    {
        $text = $message->text();

        return [
            'body_text' => $text,
            'body_html' => $message->html(),
            'snippet' => $text === null ? null : Str::limit(trim(preg_replace('/\s+/', ' ', $text)), 160),
            'has_attachments' => $message->hasAttachments(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function flagAttributes(Message $message): array
    {
        return [
            'seen' => $message->isSeen(),
            'flagged' => $message->isFlagged(),
            'answered' => $message->isAnswered(),
            'draft' => $message->isDraft(),
            'deleted' => $message->isDeleted(),
        ];
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{name: string, email: string}>
     */
    private function addressesToArray(array $addresses): array
    {
        return array_map(
            fn (Address $address) => ['name' => $address->name(), 'email' => $address->email()],
            $addresses,
        );
    }
}
